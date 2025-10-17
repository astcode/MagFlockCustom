<?php

namespace MoBo;

class HealthMonitor
{
    private Registry $registry;
    private Logger $logger;
    private EventBus $eventBus;
    private ConfigManager $config;
    private array $healthHistory = [];

    public function __construct(Registry $registry, Logger $logger, EventBus $eventBus, ConfigManager $config)
    {
        $this->registry = $registry;
        $this->logger = $logger;
        $this->eventBus = $eventBus;
        $this->config = $config;
    }

    public function checkAll(): array
    {
        $this->logger->debug("Running health checks on all components", 'HEALTH');

        $results = [
            'system' => $this->checkSystem(),
            'components' => []
        ];

        foreach ($this->registry->list() as $component) {
            $name = $component['name'];
            $results['components'][$name] = $this->check($name);
        }

        $this->eventBus->emit('health.check_complete', $results);

        return $results;
    }

    public function check(string $name): array
    {
        $component = $this->registry->get($name);

        if (!$component) {
            return ['status' => 'unknown', 'message' => 'Component not found'];
        }

        $retries = $this->config->get('health.retries', 3);
        $retryDelay = $this->config->get('health.retry_delay', 5);
        $timeout = $this->config->get('health.timeout', 5);

        $lastError = null;

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $startTime = microtime(true);
                $health = $component->health();
                $duration = (microtime(true) - $startTime) * 1000;

                if ($duration > ($timeout * 1000)) {
                    throw new \RuntimeException("Health check timeout: {$duration}ms");
                }

                $status = $health['status'] ?? 'unknown';
                
                // Update registry
                $this->registry->setHealth($name, $status, $health);

                // Track history
                $this->trackHealth($name, $status, $attempt);

                // Determine final status based on history
                $finalStatus = $this->determineStatus($name);

                if ($finalStatus !== $status) {
                    $this->logger->info("Component health status changed: {$name}", 'HEALTH', [
                        'old_status' => $status,
                        'new_status' => $finalStatus
                    ]);

                    $this->eventBus->emit('health.status_changed', [
                        'name' => $name,
                        'old_status' => $status,
                        'new_status' => $finalStatus
                    ]);
                }

                return array_merge($health, ['final_status' => $finalStatus]);

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $this->logger->warning("Health check failed for {$name} (attempt {$attempt}/{$retries})", 'HEALTH', [
                    'error' => $lastError
                ]);

                if ($attempt < $retries) {
                    sleep($retryDelay);
                }
            }
        }

        // All retries failed
        $this->registry->setHealth($name, 'failed', ['error' => $lastError]);
        $this->trackHealth($name, 'failed', $retries);

        $this->eventBus->emit('health.failed', [
            'name' => $name,
            'error' => $lastError
        ]);

        return [
            'status' => 'failed',
            'error' => $lastError,
            'attempts' => $retries
        ];
    }

    private function checkSystem(): array
    {
        return [
            'status' => 'healthy',
            'uptime' => $this->getUptime(),
            'memory' => [
                'used' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit')
            ],
            'load' => sys_getloadavg()
        ];
    }

    private function trackHealth(string $name, string $status, int $attempt): void
    {
        if (!isset($this->healthHistory[$name])) {
            $this->healthHistory[$name] = [];
        }

        $this->healthHistory[$name][] = [
            'status' => $status,
            'attempt' => $attempt,
            'timestamp' => time()
        ];

        // Keep only last 10 checks
        if (count($this->healthHistory[$name]) > 10) {
            array_shift($this->healthHistory[$name]);
        }
    }

    private function determineStatus(string $name): string
    {
        if (!isset($this->healthHistory[$name])) {
            return 'unknown';
        }

        $history = array_slice($this->healthHistory[$name], -5); // Last 5 checks
        $failureThreshold = $this->config->get('health.failure_threshold', 2);
        $recoveryThreshold = $this->config->get('health.recovery_threshold', 2);

        $consecutiveFailures = 0;
        $consecutiveSuccesses = 0;

        foreach (array_reverse($history) as $check) {
            if (in_array($check['status'], ['failed', 'critical'])) {
                $consecutiveFailures++;
                $consecutiveSuccesses = 0;
            } else if ($check['status'] === 'healthy') {
                $consecutiveSuccesses++;
                $consecutiveFailures = 0;
            }
        }

        if ($consecutiveFailures >= $failureThreshold) {
            return 'failed';
        }

        if ($consecutiveSuccesses >= $recoveryThreshold) {
            return 'healthy';
        }

        // In between - degraded
        if ($consecutiveFailures > 0) {
            return 'degraded';
        }

        return $history[count($history) - 1]['status'];
    }

    private function getUptime(): string
    {
        $bootTime = $this->config->get('system.boot_time');
        
        if (!$bootTime) {
            return 'unknown';
        }

        $uptime = time() - strtotime($bootTime);
        
        $hours = floor($uptime / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        $seconds = $uptime % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function getHistory(string $name): array
    {
        return $this->healthHistory[$name] ?? [];
    }

    public function startMonitoring(): void
    {
        $interval = $this->config->get('health.check_interval', 30);

        $this->logger->info("Health monitoring started (interval: {$interval}s)", 'HEALTH');

        // This would run in a separate process/thread in production
        // For now, it's just a method that can be called periodically
    }


public function runBaselineChecks(): array
{
    // Emit a start log (defensive if logger absent)
    if (method_exists($this->logger ?? null, 'info')) {
        $this->logger->info('Running baseline health checks', 'HEALTH');
    }

    $results = [];

    // 1) Config sanity
    $requiredKeys = [
        'kernel.name',
        'kernel.version',
        'logging.level',
        'urls.app',
        'urls.mobo',
    ];
    $results['config'] = [
        'ok'      => true,
        'missing' => [],
    ];
    foreach ($requiredKeys as $key) {
        $val = null;
        try {
            $val = $this->config?->get($key);
        } catch (\Throwable $e) {
            // ignore
        }
        if ($val === null || $val === '') {
            $results['config']['ok'] = false;
            $results['config']['missing'][] = $key;
        }
    }

    // 2) Paths: logs, cache, state
    $rootDir      = \dirname(__DIR__);
    $logPath      = $this->config?->get('logging.path', $rootDir . '/storage/logs/mobo.log');
    $cacheDir     = $this->config?->get('cache.path',   $rootDir . '/storage/cache');
    $stateFile    = $this->config?->get('state.file',   $rootDir . '/storage/state/system.json');

    $results['paths'] = [
        'logs'  => ['path' => $logPath,   'writable' => false],
        'cache' => ['path' => $cacheDir,  'writable' => false],
        'state' => ['path' => $stateFile, 'writable' => false, 'readable' => false],
    ];

    // logs
    try {
        @is_dir(\dirname($logPath)) || @mkdir(\dirname($logPath), 0755, true);
        // try append
        $ok = @file_put_contents($logPath, '', FILE_APPEND) !== false;
        $results['paths']['logs']['writable'] = $ok;
    } catch (\Throwable $e) {
        // keep false
    }

    // cache
    try {
        @is_dir($cacheDir) || @mkdir($cacheDir, 0755, true);
        $tmpFile = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR . '.healthcheck_' . uniqid() . '.tmp';
        $ok = @file_put_contents($tmpFile, 'ok') !== false;
        if ($ok) @unlink($tmpFile);
        $results['paths']['cache']['writable'] = $ok;
    } catch (\Throwable $e) {
        // keep false
    }

    // state
    try {
        @is_dir(\dirname($stateFile)) || @mkdir(\dirname($stateFile), 0755, true);
        if (!is_file($stateFile)) {
            @file_put_contents($stateFile, json_encode(['system' => 'unknown'], JSON_PRETTY_PRINT));
        }
        $results['paths']['state']['readable'] = is_readable($stateFile);
        $ok = @file_put_contents($stateFile, json_encode(['touched' => date('c')], JSON_PRETTY_PRINT)) !== false;
        $results['paths']['state']['writable'] = $ok;
    } catch (\Throwable $e) {
        // keep false
    }

    // 3) Event bus pulse (emit + count handlers if available)
    $eventOk = false;
    try {
        if ($this->events ?? null) {
            // fire a cheap event; ignore handlers
            if (method_exists($this->events, 'emit')) {
                $this->events->emit('health.baseline', ['time' => time()]);
            }
            $eventOk = true;
        }
    } catch (\Throwable $e) {
        $eventOk = false;
    }
    $results['events'] = ['ok' => $eventOk];

    // 4) Registry sanity (exists & can be iterated/queried)
    $registryOk = false;
    try {
        $registryOk = (bool)($this->registry ?? null);
    } catch (\Throwable $e) {
        $registryOk = false;
    }
    $results['registry'] = ['ok' => $registryOk];

    // Overall flag
    $results['ok'] = (
        $results['config']['ok'] &&
        $results['paths']['logs']['writable'] &&
        $results['paths']['cache']['writable'] &&
        $results['paths']['state']['writable'] &&
        $results['paths']['state']['readable'] &&
        $results['events']['ok'] &&
        $results['registry']['ok']
    );

    if (method_exists($this->logger ?? null, 'info')) {
        $this->logger->info('Baseline health checks complete: ' . ($results['ok'] ? 'OK' : 'ISSUES'), 'HEALTH');
    }

    return $results;
}




}