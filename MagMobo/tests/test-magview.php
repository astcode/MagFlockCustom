<?php
// tests/test-magview.php
declare(strict_types=1);

require __DIR__ . '/TestUtil.php';

hr('MagView Test Suite');

$kernel = loadKernel();
$kernel->boot();

$reg = $kernel->registry();
$view = $reg->get('MagView');

if (!$view) { throw new RuntimeException("MagView not loaded"); }

echo "[TEST 1] Rendering dashboard...\n";
// assume MagView exposes a render(name, data) method; adjust to your API
$html = $view->render('dashboard', [
    'title' => 'MagMoBo Dashboard',
    'sections' => [
        ['name' => 'Status', 'value' => 'OK'],
        ['name' => 'Components', 'value' => implode(', ', array_keys($kernel->registry()->all()))],
    ],
]);

$path = __DIR__ . '/../storage/dashboard.html';
@mkdir(dirname($path), 0777, true);
file_put_contents($path, $html);

echo "✓ Dashboard rendered\n";
echo "  → Saved to: storage/dashboard.html\n\n";
hr('ALL TESTS PASSED! 🔥');
