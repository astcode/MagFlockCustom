<?php

declare(strict_types=1);

namespace MoBo\MagMigrate;

use MoBo\Kernel;
use RuntimeException;

final class MagMigrateFactory
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly string $projectRoot
    ) {
    }

    public function create(?string $component = null, ?string $connection = null): MagMigrateContext
    {
        $config = $this->kernel->getConfig();
        /** @var array<string, mixed> $migrationConfig */
        $migrationConfig = $config->get('migrations', []);
        $defaultComponent = (string) ($migrationConfig['default_component'] ?? 'magds');
        $component = $component ?? $defaultComponent;

        $pathsConfig = $migrationConfig['paths'] ?? [];
        if (!is_array($pathsConfig) || $pathsConfig === []) {
            throw new RuntimeException('Migration paths not configured.');
        }

        $paths = [];
        foreach ($pathsConfig as $name => $relativePath) {
            $absolute = $this->resolvePath((string) $relativePath);
            $paths[(string) $name] = $absolute;
        }

        $databaseConfig = (array) $config->get('database');
        $connectionName = $connection
            ?? (string) ($migrationConfig['connection'] ?? $databaseConfig['default'] ?? 'magdsdb');

        $pdo = ConnectionFactory::make($databaseConfig, $connectionName);
        $loader = new MigrationLoader($paths);
        $repository = new MigrationRepository(
            $pdo,
            (string) ($migrationConfig['table'] ?? 'schema_migrations')
        );

        return new MagMigrateContext(
            $component,
            $connectionName,
            new MagMigrate($pdo, $loader, $repository)
        );
    }

    private function resolvePath(string $relative): string
    {
        if (str_starts_with($relative, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $relative) === 1) {
            return $relative;
        }

        return $this->projectRoot . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
    }
}
