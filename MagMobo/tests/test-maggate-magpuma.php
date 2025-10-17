<?php
// tests/test-maggate-magpuma.php
declare(strict_types=1);

require __DIR__ . '/TestUtil.php';

hr('MagGate + MagPuma Test Suite');

$kernel = loadKernel();
$kernel->boot();

$reg = $kernel->registry();

$gate = $reg->get('MagGate');
$puma = $reg->get('MagPuma');

if (!$gate)  { throw new RuntimeException("MagGate not loaded"); }
if (!$puma)  { throw new RuntimeException("MagPuma not loaded"); }

echo "✅ MagGate loaded\n";
echo "✅ MagPuma loaded\n\n";

// Routes registered (depends on your component API)
$routes = method_exists($gate, 'routeCount') ? $gate->routeCount() : null;
if ($routes !== null) {
    printf("✅ Routes registered: %d\n\n", $routes);
}

// Simulate requests via MagGate’s internal dispatcher (no HTTP server required)
echo "TEST 1: Normal GET request\n";
echo "----------------------------\n";
$r1 = $gate->dispatch('GET', '/'); // or '/health' depending on your routes
printf("Status: %d\n", (int)($r1['status'] ?? 500));
printf("Content: %s\n\n", json_encode($r1['body'] ?? [], JSON_PRETTY_PRINT));

echo "TEST 2: SQL Injection attempt\n";
echo "----------------------------\n";
// Show that MagPuma/Guard computes a trust score (stubbed in your Mini)
$threat = $puma->analyzeRequest([
    'ip'     => '203.0.113.10',
    'ua'     => 'curl/8.5.0',
    'path'   => '/api/items?search=%27%3Bdrop+table+users--',
    'method' => 'GET',
]);
printf("IP Trust Score: %d\n", (int)($threat['trust'] ?? 0));
printf("Threat Level: %d\n", (int)($threat['level'] ?? 0));
echo "Threats:\n";
echo implode("\n", (array)($threat['signals'] ?? []))."\n\n";

echo "TEST 3: Bot detection\n";
echo "----------------------------\n";
$bot = $puma->detectBot('Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
printf("Is Bot: %s\n", ($bot['is_bot'] ?? false) ? 'YES' : 'NO');
printf("Bot Type: %s\n", $bot['type'] ?? 'unknown');
printf("Confidence: %d%%\n\n", (int)($bot['confidence'] ?? 0));

echo "TEST 4: Good bot detection\n";
echo "----------------------------\n";
$good = $puma->detectBot('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
printf("Is Good Bot: %s\n", ($good['is_good_bot'] ?? false) ? 'YES' : 'NO');
printf("Bot Type: %s\n", $good['type'] ?? 'unknown');
printf("Confidence: %d%%\n\n", (int)($good['confidence'] ?? 0));

echo "TEST 5: Fingerprinting\n";
echo "----------------------------\n";
$f = $puma->fingerprint([
    'ip' => '198.51.100.7',
    'ua' => 'Mozilla/5.0',
    'accept' => 'text/html',
]);
printf("Fingerprint: %s...\n\n", substr($f['hash'] ?? 'n/a', 0, 32));

echo "TEST 6: Adaptive response\n";
echo "----------------------------\n";
$ar = $puma->policyFor([
    'path' => '/api/items',
    'ip' => '198.51.100.7',
    'ua' => 'Mozilla/5.0',
    'method' => 'GET',
]);
printf("Action: %s\n", $ar['action'] ?? 'unknown');
printf("Rate Limit: %s\n", $ar['rate_limit'] ?? 'n/a');
printf("Should Log: %s\n\n", !empty($ar['log']) ? 'YES' : 'NO');

echo "🎉 ALL TESTS COMPLETE!\n";
