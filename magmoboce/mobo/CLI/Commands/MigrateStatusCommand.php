<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\MagMigrate\MagMigrateFactory;
use MoBo\Kernel;

final class MigrateStatusCommand extends AbstractCommand
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly string $projectRoot
    ) {
        parent::__construct('migrate:status', 'Show migration status for a component');
    }

    /**
     * @param list<string> $args
     */
    public function execute(array $args): int
    {
        $parsed = Arguments::parse($args);
        $options = $parsed['options'];

        $component = isset($options['component']) ? (string) $options['component'] : null;
        $connection = isset($options['connection']) ? (string) $options['connection'] : null;

        $factory = new MagMigrateFactory($this->kernel, $this->projectRoot);
        $context = $factory->create($component, $connection);
        $statusRows = $context->service()->status($context->component());

        if ($statusRows === []) {
            $this->writeln('No migrations found.');
            return 0;
        }

        $this->writeln(sprintf(
            "Component: %s (connection: %s)",
            $context->component(),
            $context->connection()
        ));
        $this->writeln(str_repeat('-', 80));
        $this->writeln(sprintf("%-20s %-40s %-10s %-20s", 'ID', 'Description', 'Status', 'Applied At'));
        $this->writeln(str_repeat('-', 80));

        foreach ($statusRows as $row) {
            $migration = $row['migration'];
            $status = $row['applied'] ? 'applied' : 'pending';
            $appliedAt = $row['applied_at'] ?? '-';
            $this->writeln(sprintf(
                "%-20s %-40s %-10s %-20s",
                $migration->id(),
                substr($migration->description(), 0, 40),
                $status,
                $appliedAt
            ));
        }

        $this->writeln(str_repeat('-', 80));

        return 0;
    }
}
