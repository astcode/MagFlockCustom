<?php

declare(strict_types=1);

return [
    'kernel' => [
        'type' => 'object',
        'required' => true,
        'children' => [
            'name' => ['type' => 'string', 'required' => true],
            'version' => ['type' => 'string', 'required' => true],
            'environment' => [
                'type' => 'enum',
                'values' => ['development', 'testing', 'staging', 'production'],
                'required' => true,
            ],
            'configured' => ['type' => 'boolean', 'required' => false],
        ],
    ],
    'logging' => [
        'type' => 'object',
        'required' => true,
        'children' => [
            'path' => ['type' => 'string', 'required' => true],
            'level' => [
                'type' => 'enum',
                'values' => ['debug', 'info', 'warn', 'warning', 'error', 'critical'],
                'required' => true,
            ],
            'redact_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'required' => true,
            ],
        ],
    ],
    'database' => [
        'type' => 'object',
        'required' => true,
    ],
    'health' => [
        'type' => 'object',
        'required' => true,
    ],
    'recovery' => [
        'type' => 'object',
        'required' => true,
    ],
    'services' => [
        'type' => 'object',
        'required' => false,
    ],
    'urls' => [
        'type' => 'object',
        'required' => false,
    ],
    'observability' => [
        'type' => 'object',
        'required' => false,
        'children' => [
            'metrics' => [
                'type' => 'object',
                'required' => false,
                'children' => [
                    'enabled' => ['type' => 'boolean', 'required' => false],
                    'host' => ['type' => 'string', 'required' => false],
                    'port' => ['type' => 'integer', 'required' => false],
                    'path' => ['type' => 'string', 'required' => false],
                    'file' => ['type' => 'string', 'required' => false],
                    'export_interval' => ['type' => 'number', 'required' => false],
                ],
            ],
        ],
    ],
    'magds' => [
        'type' => 'object',
        'required' => false,
        'children' => [
            'primary' => [
                'type' => 'object',
                'required' => true,
                'children' => [
                    'connection' => ['type' => 'string', 'required' => true],
                ],
            ],
            'replicas' => [
                'type' => 'array',
                'required' => false,
                'items' => [
                    'type' => 'object',
                    'children' => [
                        'connection' => ['type' => 'string', 'required' => true],
                        'priority' => ['type' => 'integer', 'required' => false],
                        'read_only' => ['type' => 'boolean', 'required' => false],
                        'auto_promote' => ['type' => 'boolean', 'required' => false],
                        'weight' => ['type' => 'integer', 'required' => false],
                        'tags' => ['type' => 'object', 'required' => false],
                        'lag_threshold_seconds' => ['type' => 'integer', 'required' => false],
                    ],
                ],
            ],
            'failover' => [
                'type' => 'object',
                'required' => false,
                'children' => [
                    'enabled' => ['type' => 'boolean', 'required' => false],
                    'failure_threshold' => ['type' => 'integer', 'required' => false],
                    'heartbeat_interval_seconds' => ['type' => 'integer', 'required' => false],
                    'retry_interval_seconds' => ['type' => 'integer', 'required' => false],
                    'max_retries' => ['type' => 'integer', 'required' => false],
                    'quarantine_seconds' => ['type' => 'integer', 'required' => false],
                    'cooldown_seconds' => ['type' => 'integer', 'required' => false],
                    'preferred_tags' => ['type' => 'object', 'required' => false],
                ],
            ],
            'fencing' => [
                'type' => 'object',
                'required' => false,
                'children' => [
                    'grace_period_seconds' => ['type' => 'integer', 'required' => false],
                    'session_timeout_seconds' => ['type' => 'integer', 'required' => false],
                ],
            ],
            'health' => [
                'type' => 'object',
                'required' => false,
                'children' => [
                    'read_timeout_seconds' => ['type' => 'integer', 'required' => false],
                    'write_timeout_seconds' => ['type' => 'integer', 'required' => false],
                ],
            ],
        ],
    ],
    'migrations' => [
        'type' => 'object',
        'required' => false,
        'children' => [
            'default_component' => ['type' => 'string', 'required' => false],
            'connection' => ['type' => 'string', 'required' => false],
            'table' => ['type' => 'string', 'required' => false],
            'paths' => [
                'type' => 'object',
                'required' => true,
            ],
        ],
    ],
];
