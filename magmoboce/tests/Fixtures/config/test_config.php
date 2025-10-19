<?php

declare(strict_types=1);

return [
    'kernel' => [
        'name' => 'MagMoBoCE-Test',
        'version' => '0.1.0-test',
        'environment' => 'testing',
    ],
    'logging' => [
        'path' => __DIR__ . '/../../runtime/logs/kernel.log',
        'level' => 'info',
    ],
    'database' => [
        'default' => 'magds',
        'connections' => [
            'magds' => [
                'driver' => 'pgsql',
                'host' => getenv('PGADMIN_HOST') ?: '127.0.0.1',
                'port' => getenv('PGADMIN_PORT') ?: '5433',
                'database' => getenv('PGADMIN_DATABASE') ?: 'magdsdb',
                'username' => getenv('PGADMIN_USERNAME') ?: 'magdsdb_admin',
                'password' => getenv('PGADMIN_PASSWORD') ?: 'magdsdb_admin',
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
    'system' => [
        'boot_time' => null,
        'timezone' => 'UTC',
    ],
    'components' => [],
    'security' => [
        'capabilities' => [
            'kernel.component.start' => true,
            'kernel.component.stop' => true,
            'kernel.config.reload' => true,
            'kernel.audit.read' => true,
        ],
        'audit_log' => [
            'path' => __DIR__ . '/../../runtime/logs/audit.log',
        ],
        'default_actor' => 'test-system',
    ],
    'observability' => [
        'metrics' => [
            'enabled' => false,
            'file' => __DIR__ . '/../../runtime/metrics.prom',
            'path' => '/metrics',
            'host' => '127.0.0.1',
            'port' => 9510,
        ],
    ],
];
