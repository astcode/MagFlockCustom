<?php

declare(strict_types=1);

namespace Tests\Kernel;

use MoBo\BootManager;
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

final class BootManagerTest extends TestCase
{
    private string $projectRoot;
    private string $runtimePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 2);
        $this->runtimePath = $this->projectRoot . '/tests/runtime';

        $this->prepareDirectories([
            $this->runtimePath,
            $this->runtimePath . '/logs',
            $this->runtimePath . '/state',
            $this->runtimePath . '/cache',
            $this->projectRoot . '/storage/logs',
            $this->projectRoot . '/storage/backups',
            $this->projectRoot . '/storage/cache',
            $this->projectRoot . '/storage/state',
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanDirectory($this->runtimePath . '/logs');
        $this->cleanDirectory($this->runtimePath . '/state');
        $this->cleanDirectory($this->runtimePath . '/cache');
        $stateFile = $this->projectRoot . '/storage/state/system.json';
        if (file_exists($stateFile)) {
            unlink($stateFile);
        }

        parent::tearDown();
    }

    public function testBootCompletesWithHealthyComponents(): void
    {
        $bootManager = $this->makeBootManager('test_config.php');
        $registry = $this->getRegistry($bootManager);

        $registry->register(new FakeComponent('TestCPU'));
        $registry->register(new FakeComponent('TestGPU', '0.1.0', ['TestCPU']));

        ob_start();
        $result = $bootManager->boot();
        ob_end_clean();

        self::assertTrue($result);
        self::assertSame('running', $this->getStateManager($bootManager)->getSystemState());
    }

    public function testBootFailsWhenConfigurationInvalid(): void
    {
        $bootManager = $this->makeBootManager('invalid_config.php');

        ob_start();
        $result = $bootManager->boot();
        ob_end_clean();

        self::assertFalse($result);
        self::assertSame('stopped', $this->getStateManager($bootManager)->getSystemState());
    }

    public function testBootFailsWhenComponentBootThrows(): void
    {
        $bootManager = $this->makeBootManager('test_config.php');
        $registry = $this->getRegistry($bootManager);

        $registry->register(new FakeComponent('Healthy'));
        $registry->register(new FailingComponent('Broken', 'boot'));

        ob_start();
        $result = $bootManager->boot();
        ob_end_clean();

        self::assertFalse($result);
    }

    private function makeBootManager(string $fixture): BootManager
    {
        $logger = new Logger($this->runtimePath . '/logs/test.log', 'info');
        $config = new ConfigManager($logger);
        $config->load(__DIR__ . "/../Fixtures/config/{$fixture}");
        $config->set('security.audit_log.path', $this->runtimePath . '/logs/audit.log');
        $this->ensureSecurityCapabilities($config);

        $telemetry = new Telemetry();
        $eventBus = new EventBus($logger, $telemetry);
        $registry = new Registry($logger, $eventBus);
        $auditWriter = new AuditWriter($config, $logger);
        $capabilityGate = new CapabilityGate($config, $logger, $auditWriter, $eventBus);
        $lifecycle = new LifecycleManager(
            $registry,
            $logger,
            $eventBus,
            $config,
            $telemetry,
            $capabilityGate,
            $auditWriter
        );
        $state = new StateManager($this->runtimePath . '/state/system.json', $logger);
        new CacheManager($this->runtimePath . '/cache', $logger);

        return new BootManager($config, $logger, $eventBus, $registry, $lifecycle, $state, $telemetry);
    }

    private function getRegistry(BootManager $bootManager): Registry
    {
        $property = new \ReflectionProperty(BootManager::class, 'registry');
        $property->setAccessible(true);

        /** @var Registry $registry */
        $registry = $property->getValue($bootManager);

        return $registry;
    }

    private function getStateManager(BootManager $bootManager): StateManager
    {
        $property = new \ReflectionProperty(BootManager::class, 'state');
        $property->setAccessible(true);

        /** @var StateManager $state */
        $state = $property->getValue($bootManager);

        return $state;
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

        $files = array_diff(scandir($directory) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->cleanDirectory($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }

    private function ensureSecurityCapabilities(ConfigManager $config): void
    {
        $capabilities = $config->get('security.capabilities', null);
        if (!is_array($capabilities) || $capabilities === []) {
            $config->set('security.capabilities', [
                'kernel.component.start' => true,
                'kernel.component.stop' => true,
                'kernel.config.reload' => true,
                'kernel.audit.read' => true,
            ]);
        }
    }
}
