<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use Components\MagDB\MagDB;
use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\Kernel;

final class MagDSFailoverCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('magds:failover', 'Trigger MagDS failover checks or promotions');
    }

    /**
     * @param list<string> $args
     */
    public function execute(array $args): int
    {
        $parsed = Arguments::parse($args);
        $options = $parsed['options'];

        $kernel = Kernel::getInstance();
        $component = $kernel->get('MagDB');
        if (!$component instanceof MagDB) {
            $this->error('MagDB component not registered.');
            return 1;
        }

        $manager = $component->getFailoverManager();
        if ($manager === null) {
            $this->error('Failover manager unavailable.');
            return 1;
        }

        $force = isset($options['force']);

        if (isset($options['promote'])) {
            $target = (string) $options['promote'];
            $success = $manager->forcePromote($target, $force);
            if (!$success) {
                $this->error("Promotion to {$target} failed.");
                return 1;
            }
            $this->writeln("Promoted {$target} as new primary.");
            return 0;
        }

        $auto = $manager->heartbeat(autoPromote: true);
        if ($auto) {
            $this->writeln('Heartbeat completed. Primary healthy or failover promoted.');
            return 0;
        }

        $this->error('No suitable replica available for promotion.');
        return 1;
    }
}
