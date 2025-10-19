#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    fwrite(STDERR, "[metrics-dump] Composer autoload not found at {$autoload}\n");
    exit(1);
}

require $autoload;

$metricsPath = $root . '/storage/telemetry/metrics.prom';

if (!file_exists($metricsPath)) {
    fwrite(
        STDERR,
        "[metrics-dump] Metrics file not found at {$metricsPath}. " .
        "Ensure the kernel is running with observability enabled.\n"
    );
    exit(1);
}

$contents = file_get_contents($metricsPath);

if ($contents === false) {
    fwrite(STDERR, "[metrics-dump] Unable to read metrics file at {$metricsPath}\n");
    exit(1);
}

fwrite(STDOUT, $contents);
