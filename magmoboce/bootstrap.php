<?php

require_once __DIR__ . '/vendor/autoload.php';

use MoBo\Kernel;

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '1');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function($exception) {
    echo "FATAL ERROR: " . $exception->getMessage() . "\n";
    echo $exception->getTraceAsString() . "\n";
    exit(1);
});

// Load .env file
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes
            $value = trim($value, '"\'');
            
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
}

// Helper function to get environment variables
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert string booleans
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        
        return $value;
    }
}

// Get kernel instance (singleton)
$kernel = Kernel::getInstance();

// Initialize with layered config directory
$kernel->initialize(__DIR__ . '/config/mobo.php');

// Auto-register components from config manager
$configManager = $kernel->getConfig();
$components = $configManager->get('components', []);
$databaseConfig = (array) $configManager->get('database', []);

foreach ($components as $name => $componentConfig) {
    if (!($componentConfig['enabled'] ?? true)) {
        continue;
    }

    $class = $componentConfig['class'] ?? null;
    if (!$class || !class_exists($class)) {
        continue;
    }

    $component = new $class();
    $kernel->register($component);

    if ($name === 'MagDB' && !empty($databaseConfig)) {
        $component->configure($databaseConfig);
    }
}

// Global helper function
if (!function_exists('app')) {
    function app(): Kernel
    {
        return Kernel::getInstance();
    }
}

return $kernel;
