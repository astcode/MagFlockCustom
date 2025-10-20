<?php

declare(strict_types=1);

namespace Tests\MagDS;

use Components\MagDB\MagDB;
use MoBo\Kernel;
use PHPUnit\Framework\TestCase;
use Tests\Kernel\KernelHarness;

final class MagDBBackupTest extends TestCase
{
    private ?Kernel $kernel = null;
    private string $runtimeRoot;
    private string $sourceFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runtimeRoot = __DIR__ . '/../Fixtures/runtime/backup';
        $this->sourceFile = $this->runtimeRoot . '/state/system.json';

        $this->cleanRuntime();
        $this->prepareRuntime();
    }

    protected function tearDown(): void
    {
        if ($this->kernel !== null) {
            KernelHarness::shutdown($this->kernel);
            $this->kernel = null;
        }

        $this->cleanRuntime();
        parent::tearDown();
    }

    public function testBackupRunAndVerify(): void
    {
        $manager = $this->bootAndGetManager();

        $result = $manager->runBackup('unit');
        self::assertArrayHasKey('id', $result);
        self::assertArrayHasKey('path', $result);

        $manifestPath = $result['path'] . DIRECTORY_SEPARATOR . 'manifest.json';
        self::assertFileExists($manifestPath);

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        self::assertIsArray($manifest);
        self::assertArrayHasKey('datasets', $manifest);
        self::assertArrayHasKey('kernel_state', $manifest['datasets']);

        $verified = $manager->verifyBackup($result['id']);
        self::assertTrue($verified, 'Backup should verify successfully');
    }

    public function testBackupRetentionPrunesOldBackups(): void
    {
        $manager = $this->bootAndGetManager();

        $first = $manager->runBackup('first');
        sleep(1);
        $second = $manager->runBackup('second');
        sleep(1);
        $third = $manager->runBackup('third');

        $backups = $manager->getBackups();
        self::assertCount(2, $backups, 'Retention should keep only two most recent backups');
        self::assertFalse(in_array($first['id'], $backups, true), 'Oldest backup should be pruned');
        self::assertTrue(in_array($second['id'], $backups, true));
        self::assertTrue(in_array($third['id'], $backups, true));
    }

    public function testRestoreReplacesDataset(): void
    {
        $manager = $this->bootAndGetManager();

        file_put_contents($this->sourceFile, json_encode(['before' => 'snapshot'], JSON_PRETTY_PRINT));

        $result = $manager->runBackup('restore-test');

        file_put_contents($this->sourceFile, json_encode(['after' => 'mutation'], JSON_PRETTY_PRINT));
        $manager->restoreBackup($result['id']);

        $restoredContent = json_decode((string) file_get_contents($this->sourceFile), true);
        self::assertArrayHasKey('before', $restoredContent);
        self::assertSame('snapshot', $restoredContent['before']);
    }

    private function bootAndGetManager()
    {
        if ($this->kernel === null) {
            ob_start();
            try {
                $this->kernel = KernelHarness::bootWith(
                    [],
                    includeConfigured: true,
                    configuredNames: ['MagDB'],
                    configPath: __DIR__ . '/../Fixtures/config/test_magds_backup.php',
                    logLevel: 'error'
                );
            } finally {
                ob_end_clean();
            }
        }

        $magdb = $this->kernel->get('MagDB');
        self::assertInstanceOf(MagDB::class, $magdb);

        $manager = $magdb->getBackupManager();
        self::assertNotNull($manager, 'Backup manager should be initialised');

        return $manager;
    }

    private function prepareRuntime(): void
    {
        $stateDir = dirname($this->sourceFile);
        if (!is_dir($stateDir) && !mkdir($stateDir, 0775, true) && !is_dir($stateDir)) {
            self::fail('Unable to prepare runtime state directory for backup tests.');
        }

        file_put_contents($this->sourceFile, json_encode(['seed' => 'value'], JSON_PRETTY_PRINT));
    }

    private function cleanRuntime(): void
    {
        if (!is_dir($this->runtimeRoot)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->runtimeRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getPathname());
            } else {
                @unlink($fileInfo->getPathname());
            }
        }

        @rmdir($this->runtimeRoot);
    }
}
