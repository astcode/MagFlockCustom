<?php

namespace MoBo;

class HealthMonitor
{
    private Registry $registry;
    private Logger $logger;
    private EventBus $eventBus;
    private ConfigManager $config;
    private array $healthHistory = [];
    private ?Telemetry $telemetry;

    public function __construct(
        Registry $registry,
        Logger $logger,
        EventBus $eventBus,
        ConfigManager $config,
        ?Telemetry $telemetry = null
    )
    {
        $this->registry = $registry;
        $this->logger = $logger;
        $this->eventBus = $eventBus;
        $this->config = $config;
        $this->telemetry = $telemetry;
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
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];

        return [
            'status' => 'healthy',
            'uptime' => $this->getUptime(),
            'memory' => [
                'used' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit')
            ],
            'load' => $load
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

        $sawFailure = false;

        foreach (array_reverse($history) as $check) {
            if (in_array($check['status'], ['failed', 'critical'])) {
                $consecutiveFailures++;
                $consecutiveSuccesses = 0;
                $sawFailure = true;

                if ($consecutiveFailures >= $failureThreshold) {
                    return 'failed';
                }
            } elseif ($check['status'] === 'healthy') {
                $consecutiveSuccesses++;
                $consecutiveFailures = 0;

                if ($consecutiveSuccesses >= $recoveryThreshold) {
                    return 'healthy';
                }
            }
        }

        if ($sawFailure) {
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
}
