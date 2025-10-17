<?php
return [
    // Default connection (env overrides)
    'default' => getenv('DB_CONNECTION') ?: 'magdsdb',

    'connections' => [
        // MagDS (primary)
        'magdsdb' => [
            'driver'   => 'pgsql',
            'host'     => getenv('PGADMIN_HOST')     ?: '127.0.0.1',
            'port'     => getenv('PGADMIN_PORT')     ?: '5433',
            'database' => getenv('PGADMIN_DATABASE') ?: 'magdsdb',
            'username' => getenv('PGADMIN_USERNAME') ?: 'magdsdb_admin',
            'password' => getenv('PGADMIN_PASSWORD') ?: 'magdsdb_admin',
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
        ],

        // App/UI DB (currently named "magui")
        'magui' => [
            'driver'   => 'pgsql',
            'host'     => getenv('DB_HOST')     ?: '127.0.0.1',
            'port'     => getenv('DB_PORT')     ?: '5433',
            'database' => getenv('DB_DATABASE') ?: 'magui_app',
            'username' => getenv('DB_USERNAME') ?: 'magui_admin',
            'password' => getenv('DB_PASSWORD') ?: 'magui_admin',
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
        ],

        // Fallback generic Postgres
        'postgres' => [
            'driver'   => 'pgsql',
            'host'     => getenv('DB_HOST')     ?: '127.0.0.1',
            'port'     => getenv('DB_PORT')     ?: '5433',
            'database' => getenv('DB_DATABASE') ?: 'magdsdb',
            'username' => getenv('DB_USERNAME') ?: 'magdsdb_admin',
            'password' => getenv('DB_PASSWORD') ?: 'magdsdb_admin',
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
        ],
    ],

    // Connection pool guidance (your client will implement these)
    'pool' => [
        'min'          => 2,
        'max'          => 10,
        'idle_timeout' => 60,
    ],
];
