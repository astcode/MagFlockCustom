<?php

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║           MagView Test Suite                              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    echo "[TEST 1] Loading kernel...\n";
    $kernel = require __DIR__ . '/bootstrap.php';
    echo "✓ Kernel loaded\n\n";

    echo "[TEST 2] Registering MagView component...\n";
    if($kernel->getRegistry()->has('MagView')) {
        echo "✓ MagView already registered in bootstrap\n\n";
        $magView = $kernel->get('MagView');
        echo "✓ Retrieved from registry\n\n";
    } else {
        echo "⚠ MagView not registered, registering now...\n";
        $magView = new \Components\MagView\MagView();
        $kernel->register($magView);
        echo "✓ MagView registered\n\n";
    }   

    echo "✓ MagView registered\n\n";

    echo "[TEST 3] Booting kernel...\n";
    $kernel->boot();
    echo "✓ Kernel booted\n\n";

    echo "[TEST 4] Rendering dashboard...\n";
    
    $registry = $kernel->getRegistry();
    $magViewInstance = $registry->get('MagView');
    
    $html = $magViewInstance->render('dashboard', [
        'title' => 'MagFlock Dashboard',
        'subtitle' => 'MoBoMini Kernel v' . $kernel->getVersion(),
        'stats' => [
            [
                'title' => 'Uptime',
                'value' => '00:00:05',
                'label' => 'System running'
            ],
            [
                'title' => 'Components',
                'value' => count($registry->list()),
                'label' => 'Active components'
            ],
            [
                'title' => 'Memory',
                'value' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'label' => 'Memory usage'
            ],
            [
                'title' => 'Status',
                'value' => '✓',
                'label' => 'All systems operational'
            ]
        ],
        'components' => $registry->list()
    ]);
    
    // Save to file
    file_put_contents(__DIR__ . '/dashboard.html', $html);
    
    echo "✓ Dashboard rendered\n";
    echo "  → Saved to: dashboard.html\n\n";

    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║              ALL TESTS PASSED! 🔥                          ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";

    echo "Open dashboard.html in your browser to see the result!\n\n";

    $kernel->shutdown();

} catch (\Throwable $e) {
    echo "\n❌ TEST FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}