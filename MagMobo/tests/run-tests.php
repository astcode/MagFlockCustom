<?php
// tests/run-tests.php
$tests = [
  'test-boot.php',
  'test-magdb.php',
  'test-maggate-magpuma.php',
  'test-magview.php',
];

foreach ($tests as $t) {
    echo "\n>>> Running $t\n";
    $ret = 0;
    passthru(PHP_BINARY . ' ' . __DIR__ . DIRECTORY_SEPARATOR . $t, $ret);
    if ($ret !== 0) {
        echo "✗ $t FAILED (exit $ret)\n";
        exit($ret);
    }
    echo "✓ $t OK\n";
}
echo "\nALL TESTS OK.\n";
