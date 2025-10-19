<?php

declare(strict_types=1);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$metricsPath = getenv('MOBO_METRICS_PATH') ?: '/metrics';
$metricsFile = getenv('MOBO_METRICS_FILE') ?: __DIR__ . '/../storage/telemetry/metrics.prom';

$requestedPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';

if ($requestedPath !== $metricsPath) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found\n";
    return;
}

header('Content-Type: text/plain; version=0.0.4');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!is_file($metricsFile)) {
    http_response_code(204);
    return;
}

readfile($metricsFile);
