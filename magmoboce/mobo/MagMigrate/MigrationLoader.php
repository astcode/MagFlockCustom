<?php

declare(strict_types=1);

namespace MoBo\MagMigrate;

use RuntimeException;

final class MigrationLoader
{
    /**
     * @param array<string, string> $paths component => absolute path
     */
    public function __construct(
        private readonly array $paths
    ) {
    }

    /**
     * @return list<MigrationDefinition>
     */
    public function load(string $component): array
    {
        if (!isset($this->paths[$component])) {
            throw new RuntimeException("Migration path not configured for component {$component}");
        }

        $directory = $this->paths[$component];
        if (!is_dir($directory)) {
            throw new RuntimeException("Migration directory not found: {$directory}");
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            throw new RuntimeException("Unable to glob migrations in {$directory}");
        }

        sort($files);
        $definitions = [];

        foreach ($files as $file) {
            $definition = $this->requireMigration($component, $file);
            $definitions[] = $definition;
        }

        return $definitions;
    }

    private function requireMigration(string $component, string $file): MigrationDefinition
    {
        /** @var array<string, mixed> $payload */
        $payload = require $file;

        if (!is_array($payload)) {
            throw new RuntimeException("Migration file {$file} must return an array.");
        }

        $id = (string) ($payload['id'] ?? '');
        $description = (string) ($payload['description'] ?? '');
        $up = $this->coerceStatements($payload['up'] ?? null, $file, 'up');
        $down = $this->coerceStatements($payload['down'] ?? [], $file, 'down');

        if ($id === '') {
            throw new RuntimeException("Migration {$file} is missing an id.");
        }

        if ($description === '') {
            throw new RuntimeException("Migration {$file} is missing a description.");
        }

        return new MigrationDefinition($component, $id, $description, $up, $down);
    }

    /**
     * @return list<string>
     */
    private function coerceStatements(mixed $value, string $file, string $key): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            return [trim($value)];
        }

        if (!is_array($value)) {
            throw new RuntimeException("Migration {$file} has invalid {$key} statements.");
        }

        $statements = [];
        foreach ($value as $statement) {
            if (!is_string($statement)) {
                throw new RuntimeException("Migration {$file} contains non-string {$key} statement.");
            }
            $trimmed = trim($statement);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
        }

        return $statements;
    }
}
