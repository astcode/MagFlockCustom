<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\MagMigrate\MagMigrateFactory;
use MoBo\Kernel;
use RuntimeException;

final class MigrateBaselineCommand extends AbstractCommand
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly string $projectRoot
    ) {
        parent::__construct('migrate:baseline', 'Mark migrations as applied without executing them');
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
        $target = isset($options['target']) ? (string) $options['target'] : null;

        if ($target === null) {
            $this->error('Baseline requires --target=<migration_id>.');
            return 1;
        }

        $factory = new MagMigrateFactory($this->kernel, $this->projectRoot);
        $context = $factory->create($component, $connection);
        $service = $context->service();

        try {
            $marked = $service->baseline($context->component(), $target);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return 1;
        }

        foreach ($marked as $migration) {
            $this->writeln(sprintf(
                'Marked %s - %s as applied (baseline)',
                $migration->id(),
                $migration->description()
            ));
        }

        return 0;
    }
}
