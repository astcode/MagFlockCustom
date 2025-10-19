<?php

declare(strict_types=1);

return [
    'migrations' => [
        'default_component' => 'magds',
        'connection' => 'magdsdb',
        'table' => 'schema_migrations',
        'paths' => [
            'magds' => 'migrations/magds',
        ],
    ],
];
