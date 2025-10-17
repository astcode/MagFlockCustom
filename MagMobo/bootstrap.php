<?php
// bootstrap.php (MagMobo root)
require __DIR__ . '/vendor/autoload.php';

if (!function_exists('env')) {
    function env($key, $default = null) {
        $v = getenv($key);
        return ($v === false || $v === null) ? $default : $v;
    }
}

// (Optional) load .env if you use vlucas/phpdotenv
if (file_exists(__DIR__ . '/.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();
    } catch (\Throwable $e) {
        // proceed without halting
    }
}

$config = require __DIR__ . '/config/mobo.php';
// pass $config into your Kernel constructor if needed
