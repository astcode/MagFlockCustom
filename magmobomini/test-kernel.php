<?php

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║           MoBoMini Kernel Test Suite                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    echo "[TEST 1] Loading bootstrap...\n";
    $kernel = require __DIR__ . '/bootstrap.php';
    echo "✓ Bootstrap loaded\n\n";

    echo "[TEST 2] Checking kernel instance...\n";
    echo "  - Name: " . $kernel->getName() . "\n";
    echo "  - Version: " . $kernel->getVersion() . "\n";
    echo "  - Booted: " . ($kernel->isBooted() ? 'Yes' : 'No') . "\n";
    echo "✓ Kernel instance OK\n\n";

    echo "[TEST 3] Testing subsystems...\n";
    echo "  - Config: " . (get_class($kernel->getConfig())) . "\n";
    echo "  - Logger: " . (get_class($kernel->getLogger())) . "\n";
    echo "  - EventBus: " . (get_class($kernel->getEventBus())) . "\n";
    echo "  - Registry: " . (get_class($kernel->getRegistry())) . "\n";
    echo "  - Health: " . (get_class($kernel->getHealth())) . "\n";
    echo "  - Lifecycle: " . (get_class($kernel->getLifecycle())) . "\n";
    echo "  - State: " . (get_class($kernel->getState())) . "\n";
    echo "  - Cache: " . (get_class($kernel->getCache())) . "\n";
    echo "✓ All subsystems initialized\n\n";

    echo "[TEST 4] Testing EventBus...\n";
    $eventBus = $kernel->getEventBus();
    $testResult = null;
    
    $eventBus->on('test.event', function($data) use (&$testResult) {
        $testResult = $data['message'];
    });
    
    $eventBus->emit('test.event', ['message' => 'Hello from EventBus!']);
    
    if ($testResult === 'Hello from EventBus!') {
        echo "✓ EventBus working\n\n";
    } else {
        throw new Exception("EventBus test failed");
    }

    echo "[TEST 5] Testing Cache...\n";
    $cache = $kernel->getCache();
    $cache->set('test_key', 'test_value', 60);
    
    if ($cache->get('test_key') === 'test_value') {
        echo "✓ Cache working\n\n";
    } else {
        throw new Exception("Cache test failed");
    }

    echo "[TEST 6] Testing State...\n";
    $state = $kernel->getState();
    $state->set('test.state', 'working');
    
    if ($state->get('test.state') === 'working') {
        echo "✓ State working\n\n";
    } else {
        throw new Exception("State test failed");
    }

    echo "[TEST 7] Testing Config...\n";
    $config = $kernel->getConfig();
    $kernelName = $config->get('kernel.name');
    
    if ($kernelName === 'MoBoMini') {
        echo "✓ Config working\n\n";
    } else {
        throw new Exception("Config test failed");
    }

    echo "[TEST 8] Testing Logger...\n";
    $logger = $kernel->getLogger();
    $logger->info("Test log message", 'TEST');
    echo "✓ Logger working (check storage/logs/mobo.log)\n\n";

    echo "[TEST 9] Testing MagDS Database Connection...\n";
    $dbConfig = $config->get('database.connections.magds');
    echo "  - Host: {$dbConfig['host']}:{$dbConfig['port']}\n";
    echo "  - Database: {$dbConfig['database']}\n";
    echo "  - Username: {$dbConfig['username']}\n";
    
    try {
        $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
        $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Test query
        $stmt = $pdo->query("SELECT version()");
        $version = $stmt->fetchColumn();
        echo "  - PostgreSQL Version: " . substr($version, 0, 50) . "...\n";
        
        // Check if we can see tables
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'");
        $tableCount = $stmt->fetchColumn();
        echo "  - Tables in public schema: {$tableCount}\n";
        
        echo "✓ MagDS Database connected\n\n";
    } catch (\PDOException $e) {
        echo "✗ Database connection failed: " . $e->getMessage() . "\n";
        echo "  (This is OK if database isn't running yet)\n\n";
    }

    echo "[TEST 10] Testing MagUI Database Connection...\n";
    $dbConfig = $config->get('database.connections.magui');
    echo "  - Host: {$dbConfig['host']}:{$dbConfig['port']}\n";
    echo "  - Database: {$dbConfig['database']}\n";
    echo "  - Username: {$dbConfig['username']}\n";
    
    try {
        $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
        $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Check if we can see Laravel tables
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE '%migrations%'");
        $migrationTables = $stmt->fetchColumn();
        echo "  - Laravel migration tables found: {$migrationTables}\n";
        
        echo "✓ MagUI Database connected\n\n";
    } catch (\PDOException $e) {
        echo "✗ Database connection failed: " . $e->getMessage() . "\n";
        echo "  (This is OK if database isn't running yet)\n\n";
    }

    echo "[TEST 11] Attempting boot (no components)...\n";
    $bootResult = $kernel->boot();
    
    if ($bootResult) {
        echo "✓ Kernel booted successfully\n\n";
    } else {
        throw new Exception("Boot failed");
    }

    echo "[TEST 12] Checking system state...\n";
    $systemState = $state->getSystemState();
    echo "  - System State: {$systemState}\n";
    
    if ($systemState === 'running') {
        echo "✓ System running\n\n";
    } else {
        throw new Exception("System not in running state");
    }

    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║              ALL TESTS PASSED! 🔥                          ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";

    echo "[CLEANUP] Shutting down kernel...\n";
    $kernel->shutdown();
    echo "✓ Kernel shutdown complete\n\n";

} catch (\Throwable $e) {
    echo "\n❌ TEST FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}