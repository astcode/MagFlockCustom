<?php

declare(strict_types=1);

return [
    'services' => [
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'password' => getenv('REDIS_PASSWORD') ?: null,
        ],
        'pusher' => [
            'host' => getenv('PUSHER_HOST') ?: '127.0.0.1',
            'port' => getenv('PUSHER_PORT') ?: 6001,
            'scheme' => getenv('PUSHER_SCHEME') ?: 'http',
            'app_id' => getenv('PUSHER_APP_ID') ?: 'magui-local',
            'key' => getenv('PUSHER_APP_KEY') ?: 'localkey',
            'secret' => getenv('PUSHER_APP_SECRET') ?: 'localsecret',
        ],
    ],
];
