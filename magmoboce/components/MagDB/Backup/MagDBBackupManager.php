<?php

declare(strict_types=1);

namespace Components\MagDB\Backup;

use Components\MagDB\MagDB;
use MoBo\EventBus;
use MoBo\Logger;
use MoBo\StateManager;
use MoBo\Telemetry;
use RuntimeException;

final class MagDBBackupManager
{
    private const STATE_KEY_LAST_BACKUP = 'magds.backup.last';

    public function __construct(
        private readonly MagDB $magdb,
        private array $config,
        private readonly ?Logger $logger,
        private readonly ?Telemetry $telemetry,
        private readonly ?StateManager $stateManager,
        private readonly EventBus $eventBus
    ) {
    }

    public function initialize(): void
    {
        $path = $this->backupRoot();
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    public function runBackup(?string $label = null): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('MagDS backups are disabled in configuration.');
        }

        $timestamp = gmdate('Ymd_His');
        $sanitisedLabel = $label !== null ? preg_replace('/[^a-zA-Z0-9_-]/', '-', $label) : null;
        $backupId = $sanitisedLabel ? "{$timestamp}_{$sanitisedLabel}" : $timestamp;
        $backupPath = $this->backupRoot() . DIRECTORY_SEPARATOR . $backupId;
        $dataPath = $backupPath . DIRECTORY_SEPARATOR . 'datasets';

        if (!mkdir($dataPath, 0775, true) && !is_dir($dataPath)) {
            throw new RuntimeException(sprintf('Unable to create backup path: %s', $backupPath));
        }

        $datasets = $this->datasets();
        $checksums = [];
        $algorithm = $this->checksumAlgorithm();

        foreach ($datasets as $dataset) {
            $source = $dataset['source'];
            $name = $dataset['name'];
            $target = $dataPath . DIRECTORY_SEPARATOR . basename($source);

            if (!file_exists($source)) {
                $this->logger?->warning('MagDS backup dataset missing source file.', 'MAGDB', [
                    'dataset' => $name,
                    'source' => $source,
                ]);
                continue;
            }

            $targetDirectory = dirname($target);
            if (!is_dir($targetDirectory)) {
                mkdir($targetDirectory, 0775, true);
            }

            if (!copy($source, $target)) {
                throw new RuntimeException(sprintf('Failed to copy dataset %s to backup', $name));
            }

            $checksum = hash_file($algorithm, $target);
            $checksums[$name] = [
                'algorithm' => $algorithm,
                'hash' => $checksum,
                'source' => $source,
                'backup' => $target,
            ];
        }

        $manifest = [
            'id' => $backupId,
            'created_at' => gmdate('c'),
            'datasets' => $checksums,
            'metadata' => [
                'label' => $label,
                'connection' => $this->magdb->getActiveConnectionName(),
            ],
        ];

        file_put_contents(
            $backupPath . DIRECTORY_SEPARATOR . 'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->stateManager?->set(self::STATE_KEY_LAST_BACKUP, $manifest);
        $this->telemetry?->incrementCounter('magdb.backups_total');
        $this->telemetry?->setGauge('magdb.last_backup_epoch', (float) time());

        $this->eventBus->emit('magdb.backup.completed', [
            'id' => $backupId,
            'timestamp' => microtime(true),
        ]);

        $this->pruneBackups();

        $this->logger?->info('MagDS backup completed.', 'MAGDB', [
            'id' => $backupId,
            'path' => $backupPath,
        ]);

        return [
            'id' => $backupId,
            'path' => $backupPath,
            'manifest' => $manifest,
        ];
    }

    public function verifyBackup(string $backupId): bool
    {
        $manifest = $this->loadManifest($backupId);
        $datasets = $manifest['datasets'] ?? [];

        $allValid = true;
        foreach ($datasets as $name => $dataset) {
            $path = $dataset['backup'] ?? null;
            $expected = $dataset['hash'] ?? null;
            $algorithm = $dataset['algorithm'] ?? $this->checksumAlgorithm();

            if ($path === null || !file_exists($path)) {
                $allValid = false;
                $this->logger?->warning('MagDS backup verification failed – dataset missing.', 'MAGDB', [
                    'backup' => $backupId,
                    'dataset' => $name,
                ]);
                continue;
            }

            $actual = hash_file($algorithm, $path);
            if (!hash_equals($expected, $actual)) {
                $allValid = false;
                $this->logger?->warning('MagDS backup checksum mismatch.', 'MAGDB', [
                    'backup' => $backupId,
                    'dataset' => $name,
                ]);
            }
        }

        $manifest['metadata']['last_verified_at'] = gmdate('c');
        file_put_contents(
            $this->manifestPath($backupId),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->eventBus->emit('magdb.backup.verify_completed', [
            'id' => $backupId,
            'success' => $allValid,
            'timestamp' => microtime(true),
        ]);

        return $allValid;
    }

    public function restoreBackup(string $backupId, bool $dryRun = false): array
    {
        $manifest = $this->loadManifest($backupId);
        $datasets = $manifest['datasets'] ?? [];
        $actions = [];

        foreach ($datasets as $name => $dataset) {
            $source = $dataset['backup'] ?? null;
            $target = $dataset['source'] ?? null;

            if ($source === null || $target === null || !file_exists($source)) {
                $this->logger?->warning('MagDS restore skipped dataset.', 'MAGDB', [
                    'dataset' => $name,
                    'backup' => $backupId,
                ]);
                continue;
            }

            $actions[] = ['dataset' => $name, 'source' => $source, 'target' => $target];

            if ($dryRun) {
                continue;
            }

            $targetDirectory = dirname($target);
            if (!is_dir($targetDirectory)) {
                mkdir($targetDirectory, 0775, true);
            }

            if (!copy($source, $target)) {
                throw new RuntimeException(sprintf('Unable to restore dataset %s from backup %s', $name, $backupId));
            }
        }

        if (!$dryRun) {
            $this->telemetry?->incrementCounter('magdb.restores_total');
            $this->telemetry?->setGauge('magdb.last_restore_epoch', (float) time());
            $this->eventBus->emit('magdb.restore.completed', [
                'id' => $backupId,
                'timestamp' => microtime(true),
            ]);

            $this->logger?->info('MagDS restore completed.', 'MAGDB', [
                'id' => $backupId,
                'datasets' => count($actions),
            ]);
        }

        return $actions;
    }

    public function getBackups(): array
    {
        $root = $this->backupRoot();
        if (!is_dir($root)) {
            return [];
        }

        $entries = array_filter(scandir($root) ?: [], static fn ($entry): bool => $entry !== '.' && $entry !== '..');
        sort($entries);
        return array_values($entries);
    }

    private function isEnabled(): bool
    {
        return (bool) ($this->config['backup']['enabled'] ?? true);
    }

    private function backupRoot(): string
    {
        $path = $this->config['backup']['path'] ?? 'storage/backups';
        return $this->magdbPath($path);
    }

    private function datasets(): array
    {
        $datasets = $this->config['backup']['datasets'] ?? [];

        if ($datasets === []) {
            $default = $this->magdbPath('storage/state/system.json');
            $datasets[] = [
                'name' => 'kernel_state',
                'source' => $default,
                'type' => 'file',
            ];
        }

        return array_map(function (array $dataset): array {
            $dataset['name'] = (string) ($dataset['name'] ?? 'unnamed');
            $dataset['source'] = $this->magdbPath((string) ($dataset['source'] ?? ''));
            return $dataset;
        }, $datasets);
    }

    private function checksumAlgorithm(): string
    {
        return (string) ($this->config['backup']['verification']['algorithm'] ?? 'sha256');
    }

    private function pruneBackups(): void
    {
        $maxCount = (int) ($this->config['backup']['retention']['max_count'] ?? 0);
        if ($maxCount <= 0) {
            return;
        }

        $backups = $this->getBackups();
        if (count($backups) <= $maxCount) {
            return;
        }

        $excess = count($backups) - $maxCount;
        for ($i = 0; $i < $excess; $i++) {
            $backupId = $backups[$i];
            $path = $this->backupRoot() . DIRECTORY_SEPARATOR . $backupId;
            $this->deleteDirectory($path);
            $this->logger?->info('MagDS backup retention pruned old backup.', 'MAGDB', [
                'id' => $backupId,
            ]);
        }
    }

    private function loadManifest(string $backupId): array
    {
        $manifestPath = $this->manifestPath($backupId);
        if (!file_exists($manifestPath)) {
            throw new RuntimeException(sprintf('Backup manifest not found: %s', $backupId));
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        return $decoded ?? [];
    }

    private function manifestPath(string $backupId): string
    {
        $path = $this->backupRoot() . DIRECTORY_SEPARATOR . $backupId;
        return $path . DIRECTORY_SEPARATOR . 'manifest.json';
    }

    private function magdbPath(string $path): string
    {
        if ($path === '') {
            return $path;
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $path;
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->deleteDirectory($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($path);
    }
}
