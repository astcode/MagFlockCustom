<?php

return [
    'default' => env('DB_CONNECTION', 'magui'),  // ← DEFAULT TO APP DB
    
    'connections' => [
        // Application database (PRIMARY - USE THIS)
        'magui' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5433'),
            'database' => env('DB_DATABASE', 'magui_app'),
            'username' => env('DB_USERNAME', 'magui_admin'),
            'password' => env('DB_PASSWORD', 'magui_admin'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ],
        
        // System/Admin database (ONLY FOR SYSTEM OPERATIONS)
        'magds' => [
            'driver' => 'pgsql',
            'host' => env('MAGDS_DB_HOST', '127.0.0.1'),
            'port' => env('MAGDS_DB_PORT', '5433'),
            'database' => env('MAGDS_DB_DATABASE', 'magdsdb'),
            'username' => env('MAGDS_DB_USERNAME', 'magdsdb_admin'),
            'password' => env('MAGDS_DB_PASSWORD', 'magdsdb_admin'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ],
    ],
    
    'pool' => [
        'min' => 2,
        'max' => 10,
        'idle_timeout' => 60,
    ],
    
    'options' => [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ],
];