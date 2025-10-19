<?php

declare(strict_types=1);

namespace MoBo\Observability;

use MoBo\ConfigManager;
use MoBo\Logger;
use MoBo\Telemetry;

final class MetricsServer
{
    private const DEFAULT_METRICS_PATH = '/metrics';

    private ?Telemetry $telemetry = null;
    private ?Logger $logger = null;
    /** @var resource|null */
    private $process = null;
    /**
     * @var array<int, resource>
     */
    private array $pipes = [];
    private bool $running = false;
    private string $metricsFile;

    public function __construct(string $metricsFile = 'storage/telemetry/metrics.prom')
    {
        $this->metricsFile = $metricsFile;
    }

    public function start(ConfigManager $config, Telemetry $telemetry, Logger $logger): void
    {
        if ($this->running) {
            return;
        }

        $settings = $config->get('observability.metrics', []);
        if (!is_array($settings)) {
            $logger->warning('Metrics configuration malformed; exporter disabled.', 'OBSERVABILITY');
            return;
        }

        $enabled = (bool) ($settings['enabled'] ?? false);
        if (!$enabled) {
            $logger->info('Metrics exporter disabled via configuration.', 'OBSERVABILITY');
            return;
        }

        $host = (string) ($settings['host'] ?? '127.0.0.1');
        $port = (int) ($settings['port'] ?? 9500);
        $path = (string) ($settings['path'] ?? self::DEFAULT_METRICS_PATH);
        $path = $path === '' ? self::DEFAULT_METRICS_PATH : $this->normalisePath($path);
        $fileSetting = (string) ($settings['file'] ?? $this->metricsFile);
        $interval = (float) ($settings['export_interval'] ?? 1.0);

        $this->metricsFile = $this->resolvePath($fileSetting);

        $telemetry->setExportInterval($interval);
        $telemetry->enableExport($this->metricsFile);
        $telemetry->forceFlush();

        $this->telemetry = $telemetry;
        $this->logger = $logger;

        $router = realpath(__DIR__ . '/../../bin/metrics-router.php');
        if ($router === false) {
            throw new \RuntimeException('Metrics router script not found.');
        }

        $command = [
            PHP_BINARY,
            '-d',
            'variables_order=EGPCS',
            '-S',
            sprintf('%s:%d', $host, $port),
            $router,
        ];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['file', $this->logPath('metrics-server.log'), 'a'],
            2 => ['file', $this->logPath('metrics-server-error.log'), 'a'],
        ];

        $environment = $_ENV;
        $environment['MOBO_METRICS_FILE'] = $this->metricsFile;
        $environment['MOBO_METRICS_PATH'] = $path;

        $process = proc_open($command, $descriptorSpec, $pipes, dirname($router), $environment);

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start metrics HTTP server.');
        }

        $this->process = $process;
        $this->pipes = $pipes;
        $this->running = true;

        if (isset($this->pipes[0]) && is_resource($this->pipes[0])) {
            fclose($this->pipes[0]);
            unset($this->pipes[0]);
        }

        $logger->info(sprintf('Metrics server listening on http://%s:%d%s', $host, $port, $path), 'OBSERVABILITY');

        register_shutdown_function(function (): void {
            $this->stop();
        });
    }

    public function stop(): void
    {
        if (!$this->running) {
            return;
        }

        if (is_resource($this->process)) {
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            proc_terminate($this->process);
            proc_close($this->process);
        }

        $this->process = null;
        $this->pipes = [];
        $this->running = false;

        if ($this->logger) {
            $this->logger->info('Metrics server stopped.', 'OBSERVABILITY');
        }
    }

    private function normalisePath(string $path): string
    {
        if ($path === '') {
            return self::DEFAULT_METRICS_PATH;
        }

        return $path[0] === '/' ? $path : '/' . $path;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            $path = $this->metricsFile;
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        $root = dirname(__DIR__, 2);

        return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function logPath(string $filename): string
    {
        $root = dirname(__DIR__, 2);
        $logDir = $root . '/storage/logs';

        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            throw new \RuntimeException(sprintf('Unable to create log directory: %s', $logDir));
        }

        return $logDir . '/' . $filename;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return $path[0] === '/' || $path[0] === '\\' || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }
}
