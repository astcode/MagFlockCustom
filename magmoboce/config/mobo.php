<?php

declare(strict_types=1);

use MoBo\Config\LayeredConfigLoader;

if (!class_exists(LayeredConfigLoader::class)) {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require $autoload;
    }
}

$loader = new LayeredConfigLoader(__DIR__, getenv('MOBO_ENV') ?: 'development');

return $loader->load();
