#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Tests\Kernel\KernelHarness;

echo "[kernel-smoke-magds] booting kernel with MagDS configuration...\n";

try {
    $kernel = KernelHarness::bootWith([], true, ['MagDB'], __DIR__ . '/../config', null);
    echo "[kernel-smoke-magds] kernel booted successfully with MagDS\n";

    $magdb = $kernel->get('MagDB');
    if ($magdb) {
        $health = $magdb->health();
        echo "[kernel-smoke-magds] MagDS health status: {$health['status']}\n";
    }

    KernelHarness::shutdown($kernel);
    echo "[kernel-smoke-magds] kernel shutdown complete\n";
    exit(0);
} catch (Throwable $throwable) {
    echo "[kernel-smoke-magds] failure: {$throwable->getMessage()}\n";
    echo $throwable->getTraceAsString() . "\n";
    exit(1);
}


