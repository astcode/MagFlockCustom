<?php

declare(strict_types=1);

namespace Tests\Kernel\State;

use MoBo\Logger;
use MoBo\StateManager;
use PHPUnit\Framework\TestCase;

final class StateManagerTest extends TestCase
{
    private string $runtimePath;

    protected function setUp(): void
    {
        parent::setUp();

        $projectRoot = dirname(__DIR__, 3);
        $this->runtimePath = $projectRoot . '/tests/runtime/state';
        $this->prepareDirectories([$this->runtimePath, $this->runtimePath . '/logs', $this->runtimePath . '/state']);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testStatePersistsAndRecoversFromCorruption(): void
    {
        $statePath = $this->runtimePath . '/state/system.json';
        $manager = new StateManager($statePath, $this->logger());

        $manager->set('system.state', 'running');
        self::assertSame('running', $manager->getSystemState());

        file_put_contents($statePath, '{corrupted json');
        $manager->load();
        self::assertSame('stopped', $manager->getSystemState());
    }

    public function testComponentStateManagement(): void
    {
        $manager = new StateManager($this->runtimePath . '/state/system.json', $this->logger());

        $manager->setComponentState('MagDS', 'running');
        self::assertSame('running', $manager->getComponentState('MagDS'));

        $manager->setComponentState('MagDS', 'stopped');
        self::assertSame('stopped', $manager->getComponentState('MagDS'));
    }

    private function logger(): Logger
    {
        return new Logger($this->runtimePath . '/logs/state.log', 'info');
    }

    private function prepareDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException("Unable to create directory: {$directory}");
            }
        }
    }

    private function cleanup(): void
    {
        if (!is_dir($this->runtimePath)) {
            return;
        }

        $objects = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->runtimePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($objects as $object) {
            if ($object->isDir()) {
                rmdir($object->getPathname());
            } else {
                unlink($object->getPathname());
            }
        }
    }
}
