<?php

declare(strict_types=1);

namespace MoBo\MagMigrate;

use PDO;

final class MigrationRepository
{
    public function __construct(
        private readonly PDO $connection,
        private readonly string $table = 'schema_migrations'
    ) {
    }

    public function ensureTable(): void
    {
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$this->table} (
    component TEXT NOT NULL,
    migration_id TEXT NOT NULL,
    description TEXT NOT NULL,
    checksum TEXT NOT NULL,
    applied_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (component, migration_id)
)
SQL;
        } else {
            $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$this->table} (
    component VARCHAR(100) NOT NULL,
    migration_id VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    applied_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (component, migration_id)
)
SQL;
        }

        $this->connection->exec($sql);
    }

    /**
     * @return array<string, array{component:string,migration_id:string,description:string,checksum:string,applied_at:string}>
     */
    public function fetchApplied(string $component): array
    {
        $stmt = $this->connection->prepare(
            "SELECT migration_id, description, checksum, applied_at FROM {$this->table} WHERE component = :component ORDER BY migration_id"
        );
        $stmt->execute(['component' => $component]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapped = [];
        foreach ($rows as $row) {
            $id = (string) $row['migration_id'];
            $mapped[$id] = [
                'component' => $component,
                'migration_id' => $id,
                'description' => (string) $row['description'],
                'checksum' => (string) $row['checksum'],
                'applied_at' => (string) $row['applied_at'],
            ];
        }

        return $mapped;
    }

    public function recordApplied(MigrationDefinition $migration): void
    {
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        $timestampExpr = $driver === 'sqlite' ? "datetime('now')" : 'CURRENT_TIMESTAMP';

        $stmt = $this->connection->prepare(
            "INSERT INTO {$this->table} (component, migration_id, description, checksum, applied_at)
             VALUES (:component, :id, :description, :checksum, {$timestampExpr})"
        );
        $stmt->execute([
            'component' => $migration->component(),
            'id' => $migration->id(),
            'description' => $migration->description(),
            'checksum' => $migration->checksum(),
        ]);
    }

    public function remove(MigrationDefinition $migration): void
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM {$this->table} WHERE component = :component AND migration_id = :id"
        );
        $stmt->execute([
            'component' => $migration->component(),
            'id' => $migration->id(),
        ]);
    }
}
