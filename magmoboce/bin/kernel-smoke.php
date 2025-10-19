#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Tests\Kernel\KernelHarness;
use Tests\Stubs\FakeComponent;

echo "[kernel-smoke] starting MagMoBoCE kernel harness...\n";

$components = [
    new FakeComponent('TestCPU'),
    new FakeComponent('TestGPU', '0.1.0', ['TestCPU']),
];

try {
    $kernel = KernelHarness::bootWith($components);
    echo "[kernel-smoke] kernel booted successfully\n";
    echo "[kernel-smoke] registered components:\n";

    foreach ($kernel->getRegistry()->list() as $component) {
        $state = $component['state'] ?? 'unknown';
        echo sprintf("  • %s (state: %s)\n", $component['name'], $state);
    }

    KernelHarness::shutdown($kernel);
    echo "[kernel-smoke] kernel shutdown complete\n";
    exit(0);
} catch (Throwable $throwable) {
    echo "[kernel-smoke] failure: {$throwable->getMessage()}\n";
    echo $throwable->getTraceAsString() . "\n";
    exit(1);
}
