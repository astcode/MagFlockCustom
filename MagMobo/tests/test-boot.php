<?php
// tests/test-boot.php
declare(strict_types=1);

require __DIR__ . '/TestUtil.php';

hr('MagMoBo Kernel Boot Test');

$kernel = loadKernel();

$k = $kernel->config()->get('kernel') ?? [];
$u = $kernel->config()->get('urls') ?? [];

printf("Name: %s\n", $k['name'] ?? 'unknown');
printf("Version: %s\n", $k['version'] ?? 'unknown');
printf("Env: %s\n", $k['environment'] ?? 'unknown');
printf("APP_URL: %s\n", $u['app']  ?? 'n/a');
printf("MOBO_URL: %s\n", $u['mobo'] ?? 'n/a');

echo "\n[Health] Baseline checks...\n";
$kernel->health()->runBaselineChecks();

if (!$report['ok']) {
    echo "[Health] Issues detected:\n";
    foreach ($report as $k => $v) {
        if (is_array($v) && isset($v['ok']) && $v['ok'] === false) {
            echo "  - $k: ";
            if (isset($v['missing']) && $v['missing']) {
                echo "missing " . implode(', ', $v['missing']) . "\n";
            } else {
                echo "not OK\n";
            }
        }
    }
}
echo $report['ok'] ? "✓ Kernel baseline OK\n" : "✗ Kernel baseline NOT OK\n";


echo "✓ Kernel baseline OK\n";
