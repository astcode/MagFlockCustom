<?php

declare(strict_types=1);

$host = getenv('PGADMIN_HOST') ?: '127.0.0.1';
$port = (int) (getenv('PGADMIN_PORT') ?: '5433');
$database = getenv('PGADMIN_DATABASE') ?: 'magdsdb';
$username = getenv('PGADMIN_USERNAME') ?: 'magdsdb_admin';
$password = getenv('PGADMIN_PASSWORD') ?: 'magdsdb_admin';

return [
    'kernel' => [
        'name' => 'MagMoBoCE-FailoverTest',
        'version' => '0.1.0-test',
        'environment' => 'testing',
    ],
    'logging' => [
        'path' => __DIR__ . '/../runtime/logs/failover.log',
        'level' => 'error',
    ],
    'database' => [
        'default' => 'magds_primary',
        'connections' => [
            'magds_primary' => [
                'driver' => 'pgsql',
                'host' => $host,
                'port' => (string) ($port + 1000), // intentionally unreachable for failure simulation
                'database' => $database,
                'username' => $username,
                'password' => $password,
            ],
            'magds_replica' => [
                'driver' => 'pgsql',
                'host' => $host,
                'port' => (string) $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
            ],
        ],
    ],
    'health' => [
        'check_interval' => 5,
        'timeout' => 2,
        'retries' => 1,
        'retry_delay' => 1,
        'failure_threshold' => 1,
        'recovery_threshold' => 1,
    ],
    'recovery' => [
        'max_restarts' => 2,
        'restart_delay' => 1,
        'backoff' => 'linear',
    ],
    'magds' => [
        'primary' => ['connection' => 'magds_primary'],
        'replicas' => [
            [
                'connection' => 'magds_replica',
                'priority' => 100,
                'read_only' => false,
                'auto_promote' => true,
            ],
        ],
        'failover' => [
            'enabled' => true,
            'failure_threshold' => 1,
            'heartbeat_interval_seconds' => 5,
            'retry_interval_seconds' => 2,
            'max_retries' => 1,
            'quarantine_seconds' => 30,
            'cooldown_seconds' => 5,
        ],
        'fencing' => [
            'grace_period_seconds' => 10,
            'session_timeout_seconds' => 30,
        ],
        'health' => [
            'read_timeout_seconds' => 3,
            'write_timeout_seconds' => 6,
        ],
    ],
    'observability' => [
        'metrics' => [
            'enabled' => false,
            'file' => __DIR__ . '/../runtime/metrics.failover.prom',
            'path' => '/metrics',
            'host' => '127.0.0.1',
            'port' => 9520,
        ],
    ],
    'components' => [
        'MagDB' => [
            'enabled' => true,
            'class' => \Components\MagDB\MagDB::class,
        ],
    ],
    'security' => [
        'capabilities' => [
            'kernel.component.start' => true,
            'kernel.component.stop' => true,
            'kernel.config.reload' => true,
            'kernel.audit.read' => true,
        ],
        'audit_log' => [
            'path' => __DIR__ . '/../runtime/logs/failover_audit.log',
        ],
        'default_actor' => 'failover-test',
    ],
];
