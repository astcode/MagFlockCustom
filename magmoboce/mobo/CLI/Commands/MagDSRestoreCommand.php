<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use Components\MagDB\MagDB;
use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\Kernel;

final class MagDSRestoreCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('magds:restore', 'Restore MagDS state from a backup manifest');
    }

    /**
     * @param list<string> $args
     */
    public function execute(array $args): int
    {
        $parsed = Arguments::parse($args);
        $options = $parsed['options'];

        $id = isset($options['id']) ? (string) $options['id'] : '';
        if ($id === '') {
            $this->error('Restore requires --id=<backup-id>.');
            return 1;
        }

        $dryRun = isset($options['dry-run']);

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

        try {
            $actions = $manager->restoreBackup($id, $dryRun);
        } catch (\Throwable $exception) {
            $this->error('Restore failed: ' . $exception->getMessage());
            return 1;
        }

        if ($dryRun) {
            $this->writeln('Dry run summary (no files modified):');
        } else {
            $this->writeln('Restore completed.');
        }

        if ($actions === []) {
            $this->writeln('No datasets were restored.');
            return 0;
        }

        foreach ($actions as $action) {
            $this->writeln(sprintf(
                ' - %s: %s -> %s',
                $action['dataset'],
                $action['source'],
                $action['target']
            ));
        }

        return 0;
    }
}
