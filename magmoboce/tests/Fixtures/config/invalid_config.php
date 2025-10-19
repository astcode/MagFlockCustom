<?php

declare(strict_types=1);

return [
    'kernel' => [
        'name' => 'MagMoBoCE-Test',
        'version' => '0.1.0-test',
        'environment' => 'testing',
    ],
    // logging.path intentionally omitted to trigger validation failure
    'logging' => [
        'level' => 'debug',
    ],
    'database' => [
        'connections' => [
            'magds' => null,
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
            'path' => __DIR__ . '/../../runtime/logs/audit.log',
        ],
        'default_actor' => 'test-system',
    ],
];
