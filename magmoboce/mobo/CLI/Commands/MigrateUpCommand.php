<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\MagMigrate\MagMigrateFactory;
use MoBo\Kernel;
use RuntimeException;

final class MigrateUpCommand extends AbstractCommand
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly string $projectRoot
    ) {
        parent::__construct('migrate:up', 'Run outstanding migrations');
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

        $factory = new MagMigrateFactory($this->kernel, $this->projectRoot);
        $context = $factory->create($component, $connection);
        $service = $context->service();

        try {
            $applied = $service->migrateUp($context->component(), $target);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return 1;
        }

        if (empty($applied)) {
            $this->writeln('Nothing to migrate.');
            return 0;
        }

        foreach ($applied as $migration) {
            $this->writeln(sprintf(
                'Applied %s - %s',
                $migration->id(),
                $migration->description()
            ));
        }

        return 0;
    }
}
