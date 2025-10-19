<?php

declare(strict_types=1);

namespace MoBo\MagMigrate;

use PDO;
use RuntimeException;

final class ConnectionFactory
{
    /**
     * @param array<string, mixed> $databaseConfig
     */
    public static function make(array $databaseConfig, string $connectionName): PDO
    {
        $connections = $databaseConfig['connections'] ?? [];
        if (!isset($connections[$connectionName])) {
            throw new RuntimeException("Database connection {$connectionName} is not configured.");
        }

        /** @var array<string, mixed> $config */
        $config = $connections[$connectionName];

        $driver = $config['driver'] ?? 'pgsql';
        $dsn = $config['dsn'] ?? null;
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($dsn === null) {
            $dsn = self::buildDsn($driver, $config);
        }

        return new PDO((string) $dsn, $username, $password, $options);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function buildDsn(string $driver, array $config): string
    {
        return match ($driver) {
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '5432',
                $config['database'] ?? ''
            ),
            'sqlite' => sprintf('sqlite:%s', $config['database'] ?? ':memory:'),
            default => throw new RuntimeException("Unsupported driver {$driver} for migrations."),
        };
    }
}
