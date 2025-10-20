<?php

declare(strict_types=1);

return [
    'magds' => [
        'primary' => [
            'connection' => 'magdsdb',
        ],
        'replicas' => [
            // Promote-ready replica template. Operators should override in environment config.
            [
                'connection' => 'magdsdb',
                'priority' => 100,
                'read_only' => true,
                'auto_promote' => false,
                'weight' => 0,
                'tags' => [
                    'region' => 'local',
                    'role' => 'async',
                ],
                'lag_threshold_seconds' => 10,
            ],
        ],
        'failover' => [
            'enabled' => true,
            'failure_threshold' => 3,
            'heartbeat_interval_seconds' => 10,
            'retry_interval_seconds' => 5,
            'max_retries' => 3,
            'quarantine_seconds' => 60,
            'cooldown_seconds' => 15,
            'preferred_tags' => [
                'region' => 'local',
            ],
            'weights' => [
                'lag_seconds' => 20.0,
                'latency_ms' => 0.2,
                'preferred_tag_bonus' => 25.0,
                'fenced_penalty' => 100.0,
            ],
        ],
        'fencing' => [
            'grace_period_seconds' => 30,
            'session_timeout_seconds' => 60,
        ],
        'health' => [
            'read_timeout_seconds' => 3,
            'write_timeout_seconds' => 6,
        ],
        'backup' => [
            'enabled' => true,
            'path' => 'storage/backups',
            'datasets' => [
                [
                    'name' => 'kernel_state',
                    'source' => 'storage/state/system.json',
                    'type' => 'file',
                ],
            ],
            'verification' => [
                'algorithm' => 'sha256',
            ],
            'retention' => [
                'max_count' => 5,
            ],
        ],
    ],
];
