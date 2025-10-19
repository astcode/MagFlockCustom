<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

return [
    'logging' => [
        'path' => $root . '/storage/logs/mobo.log',
        'level' => getenv('LOG_LEVEL') ?: 'debug',
        'redact_keys' => ['password', 'secret', 'token', 'apikey'],
    ],
];
