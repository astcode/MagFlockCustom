<?php

return [
    'kernel' => [
        'name'        => 'MagMoBo',           // ← RENAMED
        'version'     => '1.0.0-ce',          // ← Track CE
        'environment' => getenv('MOBO_ENV') ?: 'development',
        // stable ID helpful for telemetry/correlation
        'instance_id' => getenv('MOBO_INSTANCE_ID') ?: null,
    ],

    'paths' => [
        'logs'  => __DIR__ . '/../storage/logs/mobo.log',
        'cache' => __DIR__ . '/../storage/cache',
        'state' => __DIR__ . '/../storage/state/system.json',
    ],
    // allow both old/new keys:
    'logging' => [
        'path'  => __DIR__ . '/../storage/logs/mobo.log',
        'level' => getenv('LOG_LEVEL') ?: 'debug',
    ],
    'cache' => ['path' => __DIR__ . '/../storage/cache'],
    'state' => ['file' => __DIR__ . '/../storage/state/system.json'],


    // Database pack (includes defaults + pool)
    'database' => require __DIR__ . '/database.php',

    'health' => [
        'check_interval'     => 30,
        'timeout'            => 5,
        'retries'            => 3,
        'retry_delay'        => 5,
        'failure_threshold'  => 2,
        'recovery_threshold' => 2,
    ],

    'recovery' => [
        'max_restarts' => 3,
        'restart_delay'=> 10,
        'backoff'      => 'exponential', // linear|exponential
    ],

    'system' => [
        'boot_time' => null,
        'timezone'  => 'UTC',
    ],

    'services' => [
        'redis' => [
            'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port'     => getenv('REDIS_PORT') ?: 6379,
            'password' => getenv('REDIS_PASSWORD') ?: null,
        ],
        'pusher' => [
            'host'   => getenv('PUSHER_HOST')   ?: '127.0.0.1',
            'port'   => getenv('PUSHER_PORT')   ?: 6001,
            'scheme' => getenv('PUSHER_SCHEME') ?: 'http',
            'app_id' => getenv('PUSHER_APP_ID') ?: 'magui-local',
            'key'    => getenv('PUSHER_APP_KEY') ?: 'localkey',
            'secret' => getenv('PUSHER_APP_SECRET') ?: 'localsecret',
        ],
    ],

    'urls' => [
        // UPDATED defaults point to MagMobo, not Mini
        'app'  => getenv('APP_URL')  ?: 'http://magflockcustom.test/magmobo',
        'mobo' => getenv('MOBO_URL') ?: 'http://magflockcustom.test/magmobo',
    ],

    // ─────────────────────────────────────────────────────────────
    // Enterprise: Extension Kernel controls (stub for now)
    // ─────────────────────────────────────────────────────────────
    'extensions' => [
        'enabled' => true,
        // Capability catalog file or PHP array for least-privilege gating
        'capability_catalog' => __DIR__ . '/capabilities.php',
        // Extension registry (on-disk for CE)
        'registry_path' => __DIR__ . '/../storage/extensions/registry.json',
        // Audit log (append-only)
        'audit_log'     => __DIR__ . '/../storage/logs/extension_audit.log',
        // Event topics allowed to subscribe/publish
        'events' => [
            'allow_publish'   => ['database.query.executed', 'api.request', 'system.ready'],
            'allow_subscribe' => ['database.query.pre', 'component.state_changed', 'system.boot'],
        ],
        // Hard limits (prevent perf traps)
        'limits' => [
            'max_events_per_sec'  => 200,
            'max_cpu_ms_per_tick' => 10,
            'max_mem_mb'          => 32,
        ],
        // Default enforcement (deny by default)
        'default_policy' => 'deny',
    ],

    // ─────────────────────────────────────────────────────────────
    // Components & load order (deps first)
    // ─────────────────────────────────────────────────────────────
    'components' => [
    'MagDB' => ['class' => \Components\MagDB\MagDB::class, 'enabled' => true, 'config' => 'database'],
    // 'MagPuma' => ['class' => \Components\MagPuma\MagPuma::class, 'enabled' => true],
    // 'MagGate' => ['class' => \Components\MagGate\MagGate::class, 'enabled' => true],
    // 'MagView' => ['class' => \Components\MagView\MagView::class, 'enabled' => true],
    
    // Future: MagWS, MagCLI, MagAuth(PSU), MagSentinel...

    ],

];
