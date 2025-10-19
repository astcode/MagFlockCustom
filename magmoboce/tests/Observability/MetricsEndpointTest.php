<?php

declare(strict_types=1);

namespace Tests\Observability;

use PHPUnit\Framework\TestCase;
use Tests\Kernel\KernelHarness;

final class MetricsEndpointTest extends TestCase
{
    private string $configPath = '';

    protected function tearDown(): void
    {
        if ($this->configPath !== '' && file_exists($this->configPath)) {
            @unlink($this->configPath);
        }

        parent::tearDown();
    }

    public function testMetricsEndpointExposesPrometheusPayload(): void
    {
        $port = $this->acquireFreePort();
        $config = require __DIR__ . '/../Fixtures/config/test_config.php';
        $config['observability']['metrics']['enabled'] = true;
        $config['observability']['metrics']['host'] = '127.0.0.1';
        $config['observability']['metrics']['port'] = $port;
        $config['observability']['metrics']['path'] = '/metrics';
        $config['observability']['metrics']['file'] = dirname(__DIR__) . '/runtime/telemetry/metrics.prom';

        $this->configPath = $this->writeConfig($config);

        $kernel = null;
        ob_start();
        try {
            $kernel = KernelHarness::bootWith([], false, [], $this->configPath, 'error');
        } finally {
            ob_end_clean();
        }

        try {
            // Emit an event to ensure the metrics file has content.
            $kernel->getEventBus()->emit('test.event', ['source' => 'MetricsEndpointTest']);
            $kernel->getTelemetry()->forceFlush();

            $payload = $this->executeRouter(dirname(__DIR__, 2) . '/bin/metrics-router.php', dirname(__DIR__) . '/runtime/telemetry/metrics.prom');

            self::assertNotSame('', $payload, 'Metrics router did not render payload.');
            self::assertStringContainsString('kernel_boot_time_ms', $payload);
            self::assertStringContainsString('eventbus_events_total{event="test.event"}', $payload);
        } finally {
            if ($kernel !== null) {
                KernelHarness::shutdown($kernel);
            }
        }
    }

    private function writeConfig(array $config): string
    {
        $path = tempnam(sys_get_temp_dir(), 'metrics-config-');
        if ($path === false) {
            throw new \RuntimeException('Unable to allocate temp config file for metrics test.');
        }

        $export = var_export($config, true);
        file_put_contents($path, "<?php\n\nreturn {$export};\n");

        return $path;
    }

    private function acquireFreePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new \RuntimeException(sprintf('Unable to allocate ephemeral port: %s', $errstr));
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if (!is_string($name) || !str_contains($name, ':')) {
            throw new \RuntimeException('Unable to determine ephemeral port.');
        }

        $port = (int) substr($name, strrpos($name, ':') + 1);
        if ($port <= 0) {
            throw new \RuntimeException('Invalid ephemeral port allocated.');
        }

        return $port;
    }

    private function executeRouter(string $routerPath, string $metricsFile): string
    {
        $previousFile = getenv('MOBO_METRICS_FILE');
        $previousPath = getenv('MOBO_METRICS_PATH');

        putenv('MOBO_METRICS_FILE=' . $metricsFile);
        putenv('MOBO_METRICS_PATH=/metrics');
        $_SERVER['REQUEST_URI'] = '/metrics';

        ob_start();
        include $routerPath;
        $output = (string) ob_get_clean();

        if ($previousFile === false) {
            putenv('MOBO_METRICS_FILE');
        } else {
            putenv('MOBO_METRICS_FILE=' . $previousFile);
        }

        if ($previousPath === false) {
            putenv('MOBO_METRICS_PATH');
        } else {
            putenv('MOBO_METRICS_PATH=' . $previousPath);
        }

        unset($_SERVER['REQUEST_URI']);

        return $output;
    }
}
