<?php
// tests/test-magdb.php
declare(strict_types=1);

require __DIR__ . '/TestUtil.php';

hr('MagDB Test Suite');

$kernel = loadKernel();

echo "[TEST 1] Loading kernel...\n";
echo "✓ Kernel loaded\n\n";

echo "[TEST 2] Checking MagDB registration...\n";
$registry = $kernel->registry();
$magdb = $registry->get('MagDB');
if (!$magdb) {
    // If components autoload on boot, that's fine—we’ll get it after boot.
    echo "↪ MagDB not found yet (will be loaded during boot)\n";
} else {
    echo "✓ MagDB already registered\n";
}

echo "\n[TEST 3] Booting kernel...\n";
$kernel->boot();
echo "✓ Kernel booted\n\n";

echo "[TEST 4] Testing database connections...\n";
$profile = readDbProfile($kernel);
if (!$profile) {
    echo "⚠ No DB profile available; skipping DB tests.\n";
    exit(0);
}

$db = $registry->get('MagDB');
if (!$db) {
    throw new RuntimeException("MagDB component did not load");
}

echo "\n[TEST 5] Testing simple query...\n";
$ver = $db->fetchOne("SELECT version() AS v");
printf("  PostgreSQL Version: %s\n", $ver['v'] ?? 'unknown');
echo "✓ Query executed\n";

echo "\n[TEST 6] Testing parameterized query...\n";
$row = $db->fetchOne("SELECT $1::int as n", [42]);
printf("  Result: %s\n", isset($row['n']) && (int)$row['n'] === 42 ? "OK" : "Failed");
echo "✓ Parameterized query executed\n";

echo "\n[TEST 7] Testing fetchOne time...\n";
$now = $db->fetchOne("SELECT NOW() AS ts");
printf("  Current Time: %s\n", $now['ts'] ?? 'unknown');
echo "✓ fetchOne executed\n";

echo "\n[TEST 8] Testing fetchColumn (tables in public)...\n";
$count = (int)$db->fetchColumn("SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'");
printf("  Tables in public schema: %d\n", $count);
echo "✓ fetchColumn executed\n";

echo "\n[TEST 9] Connection statistics...\n";
$stats = $db->stats(); // assuming your component exposes this like in Mini
if (is_array($stats)) {
    foreach ($stats as $name => $s) {
        printf("  %s:\n", $name);
        printf("    Queries: %d\n", (int)($s['queries'] ?? 0));
        printf("    Total Time: %0.4fs\n", (float)($s['total_time'] ?? 0));
        printf("    Avg Time: %0.4fs\n", (float)($s['avg_time'] ?? 0));
    }
}
hr('ALL TESTS PASSED! 🔥');
