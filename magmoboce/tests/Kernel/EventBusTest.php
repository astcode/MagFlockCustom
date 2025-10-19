<?php

declare(strict_types=1);

namespace Tests\Kernel;

use MoBo\EventBus;
use MoBo\EventSchemaRegistry;
use MoBo\Logger;
use MoBo\Telemetry;
use PHPUnit\Framework\TestCase;

final class EventBusTest extends TestCase
{
    private string $runtimePath;
    private EventBus $eventBus;

    protected function setUp(): void
    {
        parent::setUp();

        $projectRoot = dirname(__DIR__, 2);
        $this->runtimePath = $projectRoot . '/tests/runtime';
        $this->prepareDirectories([
            $this->runtimePath,
            $this->runtimePath . '/logs',
        ]);

        $logger = new Logger($this->runtimePath . '/logs/eventbus.log', 'info');
        $telemetry = new Telemetry();
        $this->eventBus = new EventBus($logger, $telemetry);
    }

    public function testHandlersInvokeInPriorityOrder(): void
    {
        $invocationOrder = [];

        $this->eventBus->on('system.test', function () use (&$invocationOrder): void {
            $invocationOrder[] = 'low';
        }, priority: 10);

        $this->eventBus->on('system.test', function () use (&$invocationOrder): void {
            $invocationOrder[] = 'high';
        }, priority: 90);

        $this->eventBus->emit('system.test');

        self::assertSame(['high', 'low'], $invocationOrder);
    }

    public function testOnceHandlerRemovesItself(): void
    {
        $callCount = 0;

        $this->eventBus->once('component.ready', function () use (&$callCount): void {
            $callCount++;
        });

        $this->eventBus->emit('component.ready');
        $this->eventBus->emit('component.ready');

        self::assertSame(1, $callCount);
    }

    public function testHandlerRemovalById(): void
    {
        $callCount = 0;
        $id = $this->eventBus->on('component.update', function () use (&$callCount): void {
            $callCount++;
        });

        $this->eventBus->emit('component.update');
        $this->eventBus->off('component.update', $id);
        $this->eventBus->emit('component.update');

        self::assertSame(1, $callCount);
    }

    public function testEmitContinuesWhenHandlerThrows(): void
    {
        $callCount = 0;
        $this->eventBus->on('component.test', function (): void {
            throw new \RuntimeException('boom');
        });

        $this->eventBus->on('component.test', function () use (&$callCount): void {
            $callCount++;
        });

        $this->eventBus->emit('component.test');

        self::assertSame(1, $callCount);
    }

    public function testEventHistoryRetainsLatestEntriesOnly(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->eventBus->emit('event.' . $i, ['value' => $i]);
        }

        $history = $this->eventBus->getHistory(5);

        self::assertCount(5, $history);
        self::assertSame('event.10', $history[0]['event']);
        self::assertSame('event.14', $history[4]['event']);
    }

    public function testClearHandlersRemovesAllSubscribers(): void
    {
        $this->eventBus->on('a', fn () => null);
        $this->eventBus->on('b', fn () => null);

        self::assertSame(2, $this->eventBus->getHandlerCount());
        $this->eventBus->clearHandlers();
        self::assertSame(0, $this->eventBus->getHandlerCount());
    }

    public function testSchemaValidationWarnsOnMissingFields(): void
    {
        $logFile = $this->runtimePath . '/logs/schema.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        $logger = new Logger($logFile, 'info');
        $schema = new EventSchemaRegistry(['component.started' => ['name']]);
        $eventBus = new EventBus($logger, null, $schema);

        $eventBus->emit('component.started', []);

        self::assertFileExists($logFile);
        $logContent = file_get_contents($logFile);
        self::assertStringContainsString('missing required fields', $logContent);
        self::assertStringContainsString('component.started', $logContent);
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
