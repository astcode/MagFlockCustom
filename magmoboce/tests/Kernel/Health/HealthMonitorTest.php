<?php

declare(strict_types=1);

namespace Tests\Kernel\Health;

use MoBo\ConfigManager;
use MoBo\EventBus;
use MoBo\HealthMonitor;
use MoBo\Logger;
use MoBo\Registry;
use MoBo\Telemetry;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\FlakyHealthComponent;

final class HealthMonitorTest extends TestCase
{
    private string $runtimePath;
    private Logger $logger;
    private ConfigManager $config;
    private EventBus $eventBus;
    private Registry $registry;
    private HealthMonitor $monitor;
    private array $capturedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $projectRoot = dirname(__DIR__, 3);
        $this->runtimePath = $projectRoot . '/tests/runtime/health';
        $this->prepareDirectories([
            $this->runtimePath,
            $this->runtimePath . '/logs',
        ]);

        $this->logger = new Logger($this->runtimePath . '/logs/health.log', 'info');
        $this->config = new ConfigManager($this->logger);
        $this->config->load(__DIR__ . '/../../Fixtures/config/test_config.php');

        $this->config->set('health.retries', 2);
        $this->config->set('health.retry_delay', 0);
        $this->config->set('health.failure_threshold', 2);
        $this->config->set('health.recovery_threshold', 2);

        $telemetry = new Telemetry();
        $this->eventBus = new EventBus($this->logger, $telemetry);
        $this->eventBus->on('health.status_changed', fn (array $payload) => $this->capturedEvents[] = ['event' => 'health.status_changed', 'payload' => $payload]);
        $this->eventBus->on('health.failed', fn (array $payload) => $this->capturedEvents[] = ['event' => 'health.failed', 'payload' => $payload]);
        $this->eventBus->on('health.check_complete', fn (array $payload) => $this->capturedEvents[] = ['event' => 'health.check_complete', 'payload' => $payload]);

        $this->registry = new Registry($this->logger, $this->eventBus);
        $this->monitor = new HealthMonitor($this->registry, $this->logger, $this->eventBus, $this->config);
        $this->capturedEvents = [];
    }

    public function testHealthyComponentReportsRunning(): void
    {
        $component = new FlakyHealthComponent('Healthy', ['healthy', 'healthy']);
        $this->registry->register($component);

        $result = $this->monitor->check('Healthy');

        self::assertSame('healthy', $this->registry->getHealth('Healthy')['status']);
        self::assertSame('healthy', $result['final_status']);
    }

    public function testHealthStatusChangesAfterConsecutiveFailures(): void
    {
        $flaky = new FlakyHealthComponent('Flaky', ['healthy', 'failed', 'failed', 'healthy', 'healthy']);
        $this->registry->register($flaky);

        $this->monitor->check('Flaky');
        $this->monitor->check('Flaky');
        $final = $this->monitor->check('Flaky');

        self::assertSame('failed', $this->registry->getHealth('Flaky')['status']);
        self::assertSame('failed', $final['final_status']);
    }

    public function testRecoveryRequiresConsecutiveSuccesses(): void
    {
        $flaky = new FlakyHealthComponent('Recover', ['failed', 'failed', 'healthy', 'healthy']);
        $this->registry->register($flaky);

        $this->monitor->check('Recover');
        $this->monitor->check('Recover');
        $this->monitor->check('Recover');
        $final = $this->monitor->check('Recover');

        self::assertSame('healthy', $final['final_status']);
    }

    public function testHealthCheckRetriesOnExceptions(): void
    {
        $component = new FlakyHealthComponent('Retry', ['exception', 'healthy']);
        $this->registry->register($component);

        $result = $this->monitor->check('Retry');

        self::assertSame('healthy', $result['final_status']);
    }

    public function testCheckAllEmitsAggregatedResults(): void
    {
        $this->registry->register(new FlakyHealthComponent('CPU', ['healthy']));
        $results = $this->monitor->checkAll();

        self::assertArrayHasKey('system', $results);
        self::assertArrayHasKey('components', $results);
        self::assertArrayHasKey('CPU', $results['components']);
    }

    /**
     * @param string[] $directories
     */
    private function prepareDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException("Unable to create directory: {$directory}");
            }
        }
    }
}
