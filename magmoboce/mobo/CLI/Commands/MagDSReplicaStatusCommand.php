<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use Components\MagDB\MagDB;
use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\Kernel;

final class MagDSReplicaStatusCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('magds:replica-status', 'Show MagDS primary/replica health');
    }

    /**
     * @param list<string> $args
     */
    public function execute(array $args): int
    {
        Arguments::parse($args); // Currently unused but keeps interface symmetrical.

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

        $manager->heartbeat(autoPromote: false);
        $statuses = $manager->status();

        $this->writeln('MagDS Replica Status');
        $this->writeln(str_repeat('-', 100));
        $this->writeln(sprintf('%-20s %-8s %-8s %-8s %-8s %-20s', 'Connection', 'Role', 'Active', 'Healthy', 'Auto', 'Last Error'));
        $this->writeln(str_repeat('-', 100));

        foreach ($statuses as $row) {
            $this->writeln(sprintf(
                '%-20s %-8s %-8s %-8s %-8s %-20s',
                $row['name'],
                $row['role'],
                $row['active'] ? 'yes' : 'no',
                $row['healthy'] ? 'yes' : 'no',
                $row['auto_promote'] ? 'yes' : 'no',
                $row['last_error'] ? substr((string) $row['last_error'], 0, 20) : '-'
            ));
        }

        $this->writeln(str_repeat('-', 100));
        $this->writeln('Last failover: ' . ($manager->getLastFailover() ?? 'never'));

        return 0;
    }
}

