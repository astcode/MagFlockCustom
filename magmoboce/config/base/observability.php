<?php

declare(strict_types=1);

return [
    'observability' => [
        'metrics' => [
            'enabled' => true,
            'host' => '127.0.0.1',
            'port' => 9500,
            'path' => '/metrics',
            'file' => 'storage/telemetry/metrics.prom',
            'export_interval' => 1.0,
        ],
    ],
];
