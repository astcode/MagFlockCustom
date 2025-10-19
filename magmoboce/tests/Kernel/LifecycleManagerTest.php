<?php

declare(strict_types=1);

namespace Tests\Kernel;

use MoBo\CacheManager;
use MoBo\ConfigManager;
use MoBo\EventBus;
use MoBo\LifecycleManager;
use MoBo\Logger;
use MoBo\Registry;
use MoBo\Security\AuditWriter;
use MoBo\Security\CapabilityGate;
use MoBo\StateManager;
use MoBo\Telemetry;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\FakeComponent;
use Tests\Stubs\FailingComponent;

final class LifecycleManagerTest extends TestCase
{
    private string $runtimePath;
    private Logger $logger;
    private ConfigManager $config;
    private EventBus $eventBus;
    private Registry $registry;
    private LifecycleManager $lifecycle;
    private Telemetry $telemetry;
    private array $capturedEvents = [];
    private AuditWriter $auditWriter;
    private CapabilityGate $capabilityGate;
    private string $auditLog;

    protected function setUp(): void
    {
        parent::setUp();

        $projectRoot = dirname(__DIR__, 2);
        $this->runtimePath = $projectRoot . '/tests/runtime';
        $this->prepareDirectories([
            $this->runtimePath,
            $this->runtimePath . '/logs',
            $this->runtimePath . '/state',
            $this->runtimePath . '/cache',
            $projectRoot . '/storage/logs',
            $projectRoot . '/storage/backups',
            $projectRoot . '/storage/cache',
            $projectRoot . '/storage/state',
        ]);

        $this->logger = new Logger($this->runtimePath . '/logs/lifecycle.log', 'info');
        $this->config = new ConfigManager($this->logger);
        $this->config->load(__DIR__ . '/../Fixtures/config/test_config.php');
        $this->config->set('recovery.restart_delay', 0);
        $this->auditLog = $this->runtimePath . '/logs/audit.log';
        if (file_exists($this->auditLog)) {
            @unlink($this->auditLog);
        }
        $this->config->set('security.capabilities', [
            'kernel.component.start' => true,
            'kernel.component.stop' => true,
            'kernel.config.reload' => true,
            'kernel.audit.read' => true,
        ]);
        $this->config->set('security.audit_log.path', $this->auditLog);
        $this->config->set('security.default_actor', 'test-system');

        $this->telemetry = new Telemetry();
        $this->eventBus = new EventBus($this->logger, $this->telemetry);
        $this->eventBus->on('component.started', function (array $payload): void {
            $this->capturedEvents[] = ['event' => 'component.started', 'payload' => $payload];
        });
        $this->eventBus->on('component.failed', function (array $payload): void {
            $this->capturedEvents[] = ['event' => 'component.failed', 'payload' => $payload];
        });
        $this->eventBus->on('component.stopped', function (array $payload): void {
            $this->capturedEvents[] = ['event' => 'component.stopped', 'payload' => $payload];
        });

        new CacheManager($this->runtimePath . '/cache', $this->logger);
        new StateManager($this->runtimePath . '/state/system.json', $this->logger);

        $this->registry = new Registry($this->logger, $this->eventBus);
        $this->rebuildLifecycleManager();
    }

    protected function tearDown(): void
    {
        $this->capturedEvents = [];
        $this->cleanDirectory($this->runtimePath);
        if (isset($this->auditLog) && file_exists($this->auditLog)) {
            @unlink($this->auditLog);
        }

        parent::tearDown();
    }

    public function testStartComponentTransitionsToRunningAndEmitsEvent(): void
    {
        $component = new FakeComponent('TestCPU');
        $this->registry->register($component);

        $result = $this->lifecycle->start('TestCPU');

        self::assertTrue($result);
        self::assertSame('running', $this->registry->getState('TestCPU'));
        self::assertTrue($component->health()['running']);
        self::assertEventCaptured('component.started', 'TestCPU');
    }

    public function testStartFailsWhenComponentThrows(): void
    {
        $this->registry->register(new FailingComponent('BrokenCPU', 'start'));

        $result = $this->lifecycle->start('BrokenCPU');

        self::assertFalse($result);
        self::assertSame('failed', $this->registry->getState('BrokenCPU'));
        self::assertEventCaptured('component.failed', 'BrokenCPU');
    }

    public function testStartAllRespectsDependencyOrder(): void
    {
        $cpu = new FakeComponent('CPU');
        $gpu = new FakeComponent('GPU', '0.1.0', ['CPU']);
        $this->registry->register($cpu);
        $this->registry->register($gpu);

        $result = $this->lifecycle->startAll();

        self::assertTrue($result);
        self::assertSame(['start'], $cpu->lifecycle);
        self::assertSame(['start'], $gpu->lifecycle);

        $started = array_values(array_filter($this->capturedEvents, static fn (array $event): bool => $event['event'] === 'component.started'));
        $startedNames = array_map(static fn (array $event): string => $event['payload']['name'] ?? '', $started);

        self::assertSame(['CPU', 'GPU'], $startedNames);
        self::assertSame('running', $this->registry->getState('GPU'));
    }

    public function testStopComponentTransitionsToStoppedAndEmitsEvent(): void
    {
        $component = new FakeComponent('Cache');
        $this->registry->register($component);
        $this->lifecycle->start('Cache');
        $this->capturedEvents = [];

        $result = $this->lifecycle->stop('Cache');

        self::assertTrue($result);
        self::assertSame('stopped', $this->registry->getState('Cache'));
        self::assertEventCaptured('component.stopped', 'Cache');
    }

    public function testStopAllProcessesComponentsInReverseDependencyOrder(): void
    {
        $cpu = new FakeComponent('CPU');
        $gpu = new FakeComponent('GPU', '0.1.0', ['CPU']);
        $this->registry->register($cpu);
        $this->registry->register($gpu);
        $this->lifecycle->startAll();
        $cpu->lifecycle = [];
        $gpu->lifecycle = [];

        $result = $this->lifecycle->stopAll();

        self::assertTrue($result);
        self::assertSame('stopped', $this->registry->getState('CPU'));
        self::assertSame('stopped', $this->registry->getState('GPU'));
        self::assertContains('stop', $gpu->lifecycle);
        self::assertContains('stop', $cpu->lifecycle);
    }

    public function testRecoverRetriesUntilSuccessfulStart(): void
    {
        $component = new FakeComponent('Queue');
        $this->registry->register($component);
        $this->registry->setState('Queue', 'failed');

        $result = $this->lifecycle->recover('Queue');

        self::assertTrue($result);
        self::assertSame('running', $this->registry->getState('Queue'));
    }

    public function testRecoverHonorsMaxRestartAttempts(): void
    {
        $component = new FailingComponent('Fatal', 'recover');
        $this->registry->register($component);
        $this->config->set('recovery.max_restarts', 1);

        $attempt1 = $this->lifecycle->recover('Fatal');
        $attempt2 = $this->lifecycle->recover('Fatal');

        self::assertFalse($attempt1);
        self::assertFalse($attempt2);
    }

    public function testStartDeniedWhenCapabilityDisabled(): void
    {
        $capabilities = $this->config->get('security.capabilities', []);
        if (!is_array($capabilities)) {
            $capabilities = [];
        }
        $capabilities['kernel.component.start'] = false;
        $this->config->set('security.capabilities', $capabilities);
        $this->rebuildLifecycleManager();

        $component = new FakeComponent('Denied');
        $this->registry->register($component);

        $result = $this->lifecycle->start('Denied');

        self::assertFalse($result);
        self::assertSame('registered', $this->registry->getState('Denied'));
        self::assertFileExists($this->auditLog);
        $audit = file_get_contents($this->auditLog) ?: '';
        self::assertStringContainsString('kernel.capability.denied', $audit);
    }

    private function rebuildLifecycleManager(): void
    {
        $this->auditWriter = new AuditWriter($this->config, $this->logger);
        $this->capabilityGate = new CapabilityGate($this->config, $this->logger, $this->auditWriter, $this->eventBus);
        $this->lifecycle = new LifecycleManager(
            $this->registry,
            $this->logger,
            $this->eventBus,
            $this->config,
            $this->telemetry,
            $this->capabilityGate,
            $this->auditWriter
        );
    }

    private function assertEventCaptured(string $event, string $componentName): void
    {
        foreach ($this->capturedEvents as $captured) {
            if ($captured['event'] === $event && ($captured['payload']['name'] ?? null) === $componentName) {
                $this->addToAssertionCount(1);
                return;
            }
        }

        self::fail("Expected event '{$event}' for component '{$componentName}' was not captured.");
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

    private function cleanDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory) ?: [], ['.', '.gitkeep', '..']);
        foreach ($items as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanDirectory($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}










