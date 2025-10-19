<?php

declare(strict_types=1);

namespace MoBo\MagMigrate;

use PDO;
use RuntimeException;

final class MagMigrate
{
    public function __construct(
        private readonly PDO $connection,
        private readonly MigrationLoader $loader,
        private readonly MigrationRepository $repository
    ) {
        $this->repository->ensureTable();
    }

    /**
     * @return list<array{migration:MigrationDefinition, applied:bool, applied_at:?string}>
     */
    public function status(string $component): array
    {
        $applied = $this->repository->fetchApplied($component);
        $migrations = $this->loader->load($component);
        $status = [];

        foreach ($migrations as $migration) {
            $row = $applied[$migration->id()] ?? null;
            $status[] = [
                'migration' => $migration,
                'applied' => $row !== null,
                'applied_at' => $row['applied_at'] ?? null,
            ];
        }

        return $status;
    }

    /**
     * @return list<MigrationDefinition>
     */
    public function migrateUp(string $component, ?string $target = null): array
    {
        $applied = $this->repository->fetchApplied($component);
        $migrations = $this->loader->load($component);

        $toApply = [];
        foreach ($migrations as $migration) {
            if (isset($applied[$migration->id()])) {
                continue;
            }

            $toApply[] = $migration;
            if ($target !== null && $migration->id() === $target) {
                break;
            }
        }

        if ($target !== null && !empty($toApply) && end($toApply)->id() !== $target) {
            throw new RuntimeException("Target migration {$target} not found or already applied.");
        }

        return $this->applyMigrations($toApply, true);
    }

    /**
     * Roll back migrations. If target provided, roll back until *after* target stays applied.
     *
     * @return list<MigrationDefinition>
     */
    public function migrateDown(string $component, int $steps = 1, ?string $target = null): array
    {
        $applied = $this->repository->fetchApplied($component);
        if (empty($applied)) {
            return [];
        }

        $migrations = $this->loader->load($component);
        $appliedIds = array_values(array_keys($applied));
        $toRollback = [];

        // Collect in reverse order
        for ($i = count($migrations) - 1; $i >= 0; $i--) {
            $migration = $migrations[$i];
            if (!isset($applied[$migration->id()])) {
                continue;
            }

            $toRollback[] = $migration;

            if ($target !== null && $migration->id() === $target) {
                break;
            }

            if ($target === null && count($toRollback) >= $steps) {
                break;
            }
        }

        if ($target !== null && (empty($toRollback) || end($toRollback)->id() !== $target)) {
            throw new RuntimeException("Target migration {$target} not found in applied history.");
        }

        return $this->applyMigrations(array_reverse($toRollback), false);
    }

    /**
     * Mark migrations up to $target as applied without running SQL.
     *
     * @return list<MigrationDefinition>
     */
    public function baseline(string $component, string $target): array
    {
        $applied = $this->repository->fetchApplied($component);
        $migrations = $this->loader->load($component);
        $baselined = [];

        foreach ($migrations as $migration) {
            if (isset($applied[$migration->id()])) {
                if ($migration->id() === $target) {
                    break;
                }
                continue;
            }

            $this->repository->recordApplied($migration);
            $baselined[] = $migration;

            if ($migration->id() === $target) {
                break;
            }
        }

        if (empty($baselined) || end($baselined)->id() !== $target) {
            throw new RuntimeException("Target migration {$target} not found for baseline.");
        }

        return $baselined;
    }

    /**
     * @param list<MigrationDefinition> $migrations
     * @return list<MigrationDefinition>
     */
    private function applyMigrations(array $migrations, bool $directionUp): array
    {
        $applied = [];

        foreach ($migrations as $migration) {
            $this->connection->beginTransaction();
            try {
                $statements = $directionUp ? $migration->upStatements() : $migration->downStatements();
                if ($directionUp && $statements === []) {
                    throw new RuntimeException("Migration {$migration->id()} has no up statements.");
                }

                if (!$directionUp && $statements === []) {
                    throw new RuntimeException("Migration {$migration->id()} has no down statements.");
                }

                foreach ($statements as $sql) {
                    $this->connection->exec($sql);
                }

                if ($directionUp) {
                    $this->repository->recordApplied($migration);
                } else {
                    $this->repository->remove($migration);
                }

                $this->connection->commit();
                $applied[] = $migration;
            } catch (\Throwable $exception) {
                $this->connection->rollBack();
                throw $exception;
            }
        }

        return $applied;
    }
}
