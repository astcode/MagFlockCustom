<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use Components\MagDB\MagDB;
use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\Kernel;

final class MagDSReplicaUnregisterCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('magds:replica-unregister', 'Remove a MagDS replica definition');
    }

    /**
     * @param list<string> $args
     */
    public function execute(array $args): int
    {
        $parsed = Arguments::parse($args);
        $connection = $parsed['options']['connection'] ?? null;

        if ($connection === null || $connection === '') {
            $this->error('Replica unregistration requires --connection=<name>.');
            return 1;
        }

        $kernel = Kernel::getInstance();
        $component = $kernel->get('MagDB');
        if (!$component instanceof MagDB) {
            $this->error('MagDB component not registered.');
            return 1;
        }

        $component->unregisterReplica((string) $connection);
        $this->writeln("Replica {$connection} unregistered.");

        return 0;
    }
}
