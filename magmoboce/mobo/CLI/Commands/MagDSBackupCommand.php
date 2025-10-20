<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use Components\MagDB\MagDB;
use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\Kernel;

final class MagDSBackupCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('magds:backup', 'Manage MagDS backups (run, verify, list)');
    }

    /**
     * @param list<string> $args
     */
    public function execute(array $args): int
    {
        if ($args === []) {
            $this->error('Usage: magds:backup <run|verify|list> [--label=] [--id=]');
            return 1;
        }

        $action = array_shift($args);
        $parsed = Arguments::parse($args);
        $options = $parsed['options'];

        $kernel = Kernel::getInstance();
        $component = $kernel->get('MagDB');
        if (!$component instanceof MagDB) {
            $this->error('MagDB component not registered.');
            return 1;
        }

        $manager = $component->getBackupManager();
        if ($manager === null) {
            $this->error('Backup manager not available.');
            return 1;
        }

        return match ($action) {
            'run' => $this->runBackup($manager, (string) ($options['label'] ?? null)),
            'verify' => $this->verifyBackup($manager, (string) ($options['id'] ?? '')),
            'list' => $this->listBackups($manager),
            default => $this->unknownAction($action),
        };
    }

    private function runBackup(object $manager, ?string $label): int
    {
        try {
            $result = $manager->runBackup($label);
        } catch (\Throwable $exception) {
            $this->error('Backup failed: ' . $exception->getMessage());
            return 1;
        }

        $this->writeln('Backup completed: ' . $result['id']);
        $this->writeln(' Manifest: ' . $result['path'] . DIRECTORY_SEPARATOR . 'manifest.json');
        return 0;
    }

    private function verifyBackup(object $manager, string $id): int
    {
        if ($id === '') {
            $this->error('Verification requires --id=<backup-id>.');
            return 1;
        }

        try {
            $valid = $manager->verifyBackup($id);
        } catch (\Throwable $exception) {
            $this->error('Verification failed: ' . $exception->getMessage());
            return 1;
        }

        if (!$valid) {
            $this->error('Backup verification failed.');
            return 1;
        }

        $this->writeln('Backup verification succeeded.');
        return 0;
    }

    private function listBackups(object $manager): int
    {
        $backups = $manager->getBackups();
        if ($backups === []) {
            $this->writeln('No backups present.');
            return 0;
        }

        $this->writeln('Available backups:');
        foreach ($backups as $backup) {
            $this->writeln(' - ' . $backup);
        }

        return 0;
    }

    private function unknownAction(string $action): int
    {
        $this->error('Unknown action: ' . $action);
        $this->error('Usage: magds:backup <run|verify|list>');
        return 1;
    }
}
