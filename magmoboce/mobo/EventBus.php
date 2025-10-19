<?php

namespace MoBo;

use MoBo\Logger;

class EventBus
{
    private array $handlers = [];
    private Logger $logger;
    private array $eventHistory = [];
    private int $maxHistory = 100;
    private ?Telemetry $telemetry;
    private ?EventSchemaRegistry $schemaRegistry;

    public function __construct(Logger $logger, ?Telemetry $telemetry = null, ?EventSchemaRegistry $schemaRegistry = null)
    {
        $this->logger = $logger;
        $this->telemetry = $telemetry;
        $this->schemaRegistry = $schemaRegistry;
    }

    public function on(string $event, callable $handler, int $priority = 50): string
    {
        $id = uniqid('handler_', true);
        
        if (!isset($this->handlers[$event])) {
            $this->handlers[$event] = [];
        }

        $this->handlers[$event][$id] = [
            'handler' => $handler,
            'priority' => $priority
        ];

        // Sort by priority (higher first)
        uasort($this->handlers[$event], function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        $this->logger->debug("Handler registered for event: {$event}", 'EVENTBUS', ['id' => $id, 'priority' => $priority]);

        return $id;
    }

    public function off(string $event, string $id): void
    {
        if (isset($this->handlers[$event][$id])) {
            unset($this->handlers[$event][$id]);
            $this->logger->debug("Handler removed for event: {$event}", 'EVENTBUS', ['id' => $id]);
        }
    }

    public function once(string $event, callable $handler, int $priority = 50): void
    {
        $id = null;
        $id = $this->on($event, function($data) use ($event, &$id, $handler) {
            $handler($data);
            $this->off($event, $id);
        }, $priority);
    }

    public function emit(string $event, array $data = [], int $timeout = 5000): void
    {
        $timestamp = microtime(true);
        $payload = $data + ['timestamp' => $timestamp];

        if ($this->schemaRegistry) {
            $missing = $this->schemaRegistry->validate($event, $payload);
            if (!empty($missing)) {
                $this->logger->warning("Event payload missing required fields", 'EVENTBUS', [
                    'event' => $event,
                    'missing' => $missing
                ]);
            }
        }

        $this->logger->debug("Event emitted: {$event}", 'EVENTBUS', $payload);
        $this->telemetry?->incrementCounter('eventbus.events_total', 1, ['event' => $event]);

        // Add to history
        $this->addToHistory($event, $payload);

        if (!isset($this->handlers[$event])) {
            return;
        }

        $emitStart = microtime(true);
        foreach ($this->handlers[$event] as $id => $handlerData) {
            $handler = $handlerData['handler'];
            
            try {
                // Execute with timeout protection
                $startTime = microtime(true);
                $handler($payload);
                $duration = (microtime(true) - $startTime) * 1000;

                if ($duration > $timeout) {
                    $this->logger->warning("Handler exceeded timeout: {$event}", 'EVENTBUS', [
                        'id' => $id,
                        'duration' => $duration,
                        'timeout' => $timeout
                    ]);
                    $this->telemetry?->increment('events.handler.timeout');
                }

                $this->telemetry?->observeHistogram('eventbus.handler_duration_ms', $duration, ['event' => $event]);
            } catch (\Throwable $e) {
                $this->logger->error("Handler failed for event: {$event}", 'EVENTBUS', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->telemetry?->increment('events.handler.error');
            }
        }

        $this->telemetry?->recordTiming("events.emit.{$event}", (microtime(true) - $emitStart) * 1000);
    }

    private function addToHistory(string $event, array $data): void
    {
        $this->eventHistory[] = [
            'event' => $event,
            'data' => $data,
            'timestamp' => microtime(true)
        ];

        // Keep only last N events
        if (count($this->eventHistory) > $this->maxHistory) {
            array_shift($this->eventHistory);
        }
    }

    public function getHistory(int $limit = 10): array
    {
        return array_slice($this->eventHistory, -$limit);
    }

    public function clearHandlers(?string $event = null): void
    {
        if ($event === null) {
            $this->handlers = [];
            $this->logger->info("All event handlers cleared", 'EVENTBUS');
        } else {
            unset($this->handlers[$event]);
            $this->logger->info("Event handlers cleared: {$event}", 'EVENTBUS');
        }
    }

    public function getHandlerCount(?string $event = null): int
    {
        if ($event === null) {
            return array_sum(array_map('count', $this->handlers));
        }

        return isset($this->handlers[$event]) ? count($this->handlers[$event]) : 0;
    }
}
