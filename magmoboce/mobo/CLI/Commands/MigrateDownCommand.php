<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\MagMigrate\MagMigrateFactory;
use MoBo\Kernel;
use RuntimeException;

final class MigrateDownCommand extends AbstractCommand
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly string $projectRoot
    ) {
        parent::__construct('migrate:down', 'Rollback migrations');
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
        $steps = isset($options['steps']) ? (int) $options['steps'] : 1;

        if ($steps < 1) {
            $this->error('Steps must be at least 1.');
            return 1;
        }

        $factory = new MagMigrateFactory($this->kernel, $this->projectRoot);
        $context = $factory->create($component, $connection);
        $service = $context->service();

        try {
            $rolledBack = $service->migrateDown($context->component(), $steps, $target);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return 1;
        }

        if (empty($rolledBack)) {
            $this->writeln('Nothing to rollback.');
            return 0;
        }

        foreach ($rolledBack as $migration) {
            $this->writeln(sprintf(
                'Rolled back %s - %s',
                $migration->id(),
                $migration->description()
            ));
        }

        return 0;
    }
}
