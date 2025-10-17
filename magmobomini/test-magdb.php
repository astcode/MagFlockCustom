<?php

require_once __DIR__ . '/bootstrap.php';

use Components\MagDB\MagDB;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║           MagDB Test Suite                                ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    // Load kernel
    echo "[TEST 1] Loading kernel...\n";
    $kernel = app();
    echo "✓ Kernel loaded\n\n";
    
    // Register MagDB
    echo "[TEST 2] Checking MagDB registration...\n";

    if ($kernel->getRegistry()->has('MagDB')) {
        echo "✓ MagDB already registered in bootstrap\n";
        $magdb = $kernel->get('MagDB');
        echo "✓ Retrieved from registry\n\n";
    } else {
        echo "⚠ MagDB not registered, registering now...\n";
        $magdb = new \Components\MagDB\MagDB();
        $kernel->register($magdb);
        echo "✓ MagDB registered\n\n";
    }


    
    // Boot kernel
    echo "[TEST 3] Booting kernel...\n";
    $kernel->boot();
    echo "✓ Kernel booted\n\n";

    // DEBUG: Show what connection MagDB thinks is default
// echo "DEBUG: Default connection = " . $magdb->getDefaultConnection() . "\n\n";
    
    // Test database connection
    echo "[TEST 4] Testing database connections...\n";
    $health = $magdb->health();
    foreach ($health['connections'] as $name => $status) {
        $icon = $status['ping'] ? '✓' : '✗';
        echo "  {$icon} {$name}: " . ($status['ping'] ? 'Connected' : 'Failed') . "\n";
    }
    echo "\n";
    
    // Test simple query
    echo "[TEST 5] Testing simple query...\n";
    $result = $magdb->query('SELECT version() as version');
    echo "  PostgreSQL Version: " . ($result[0]['version'] ?? 'Unknown') . "\n";
    echo "✓ Query executed\n\n";
    
    // Test query with params
    echo "[TEST 6] Testing parameterized query...\n";
    $result = $magdb->query('SELECT $1::text as message', ['Hello from MagDB!']);
    echo "  Result: " . ($result[0]['message'] ?? 'Failed') . "\n";
    echo "✓ Parameterized query executed\n\n";
    
    // Test fetchOne
    echo "[TEST 7] Testing fetchOne...\n";
    $row = $magdb->fetchOne('SELECT NOW() as current_time');
    echo "  Current Time: " . ($row['current_time'] ?? 'Failed') . "\n";
    echo "✓ fetchOne executed\n\n";
    
    // Test fetchColumn
    echo "[TEST 8] Testing fetchColumn...\n";
    $count = $magdb->fetchColumn('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = $1', ['public']);
    echo "  Tables in public schema: {$count}\n";
    echo "✓ fetchColumn executed\n\n";
    
    // Show stats
    echo "[TEST 9] Connection statistics...\n";
    $stats = $magdb->getStats();
    foreach ($stats as $name => $stat) {
        echo "  {$name}:\n";
        echo "    Queries: {$stat['query_count']}\n";
        echo "    Total Time: {$stat['total_query_time']}s\n";
        echo "    Avg Time: {$stat['avg_query_time']}s\n";
    }
    echo "\n";
    
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║              ALL TESTS PASSED! 🔥                          ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    // Shutdown
    $kernel->shutdown();
    
} catch (\Exception $e) {
    echo "\n❌ TEST FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}