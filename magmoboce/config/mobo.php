<?php

return [
    'kernel' => [
        'name' => 'MoBoMini',
        'version' => '1.0.0',
        'environment' => getenv('MOBO_ENV') ?: 'development'
    ],

    'logging' => [
        'path' => __DIR__ . '/../storage/logs/mobo.log',
        'level' => getenv('LOG_LEVEL') ?: 'debug'
    ],

    'database' => require __DIR__ . '/database.php',

    'health' => [
        'check_interval' => 30,
        'timeout' => 5,
        'retries' => 3,
        'retry_delay' => 5,
        'failure_threshold' => 2,
        'recovery_threshold' => 2
    ],

    'recovery' => [
        'max_restarts' => 3,
        'restart_delay' => 10,
        'backoff' => 'exponential' // or 'linear'
    ],

    'system' => [
        'boot_time' => null,
        'timezone' => 'UTC'
    ],

    'services' => [
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'password' => getenv('REDIS_PASSWORD') ?: null
        ],
        'pusher' => [
            'host' => getenv('PUSHER_HOST') ?: '127.0.0.1',
            'port' => getenv('PUSHER_PORT') ?: 6001,
            'scheme' => getenv('PUSHER_SCHEME') ?: 'http',
            'app_id' => getenv('PUSHER_APP_ID') ?: 'magui-local',
            'key' => getenv('PUSHER_APP_KEY') ?: 'localkey',
            'secret' => getenv('PUSHER_APP_SECRET') ?: 'localsecret'
        ]
    ],

    'urls' => [
        'app' => getenv('APP_URL') ?: 'http://magflockcustom.test/magmobomini',
        'mobo' => getenv('MOBO_URL') ?: 'http://magflockcustom.test/magmobomini'
    ],

    // Component loading order (dependencies first!)
    'components' => [
        'MagDB' => [
            'class' => \Components\MagDB\MagDB::class,
            'enabled' => true,
            'config' => 'database', // ← USE EXISTING database config!
        ],
        'MagPuma' => [
            'class' => \Components\MagPuma\MagPuma::class,
            'enabled' => true,
            'config' => null, // ← No separate config needed
        ],
        'MagGate' => [
            'class' => \Components\MagGate\MagGate::class,
            'enabled' => true,
            'config' => null, // ← No separate config needed
        ],
        'MagView' => [
            'class' => \Components\MagView\MagView::class,
            'enabled' => true,
            'config' => null, // ← No separate config needed
        ],
    ]
];