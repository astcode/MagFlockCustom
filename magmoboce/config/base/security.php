<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

return [
    'security' => [
        'capabilities' => [
            'kernel.component.start' => true,
            'kernel.component.stop' => true,
            'kernel.config.reload' => true,
            'kernel.audit.read' => true,
        ],
        'audit_log' => [
            'path' => $root . '/storage/logs/audit.log',
        ],
        'default_actor' => 'system',
    ],
];
