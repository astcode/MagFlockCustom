<?php

declare(strict_types=1);

$runtimeRoot = __DIR__ . '/../runtime/backup';
$sourceFile = $runtimeRoot . '/state/system.json';

return [
    'kernel' => [
        'name' => 'MagMoBoCE-BackupTest',
        'version' => '0.1.0-test',
        'environment' => 'testing',
    ],
    'logging' => [
        'path' => $runtimeRoot . '/logs/kernel.log',
        'level' => 'error',
    ],
    'database' => [
        'default' => 'magds_primary',
        'connections' => [
            'magds_primary' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => '5433',
                'database' => 'magdsdb',
                'username' => 'magdsdb_admin',
                'password' => 'magdsdb_admin',
            ],
        ],
    ],
    'components' => [
        'MagDB' => [
            'enabled' => true,
            'class' => \Components\MagDB\MagDB::class,
        ],
    ],
    'magds' => [
        'primary' => ['connection' => 'magds_primary'],
        'backup' => [
            'enabled' => true,
            'path' => 'tests/Fixtures/runtime/backup/storage/backups',
            'datasets' => [
                [
                    'name' => 'kernel_state',
                    'source' => 'tests/Fixtures/runtime/backup/state/system.json',
                    'type' => 'file',
                ],
            ],
            'verification' => [
                'algorithm' => 'sha256',
            ],
            'retention' => [
                'max_count' => 2,
            ],
        ],
    ],
    'observability' => [
        'metrics' => [
            'enabled' => false,
            'file' => $runtimeRoot . '/metrics.prom',
            'path' => '/metrics',
            'host' => '127.0.0.1',
            'port' => 9530,
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
            'path' => $runtimeRoot . '/logs/audit.log',
        ],
        'default_actor' => 'backup-test',
    ],
];
