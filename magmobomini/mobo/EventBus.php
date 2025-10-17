<?php

namespace MoBo;

use MoBo\Logger;

class EventBus
{
    private array $handlers = [];
    private Logger $logger;
    private array $eventHistory = [];
    private int $maxHistory = 100;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
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
        $this->logger->debug("Event emitted: {$event}", 'EVENTBUS', $data);

        // Add to history
        $this->addToHistory($event, $data);

        if (!isset($this->handlers[$event])) {
            return;
        }

        foreach ($this->handlers[$event] as $id => $handlerData) {
            $handler = $handlerData['handler'];
            
            try {
                // Execute with timeout protection
                $startTime = microtime(true);
                $handler($data);
                $duration = (microtime(true) - $startTime) * 1000;

                if ($duration > $timeout) {
                    $this->logger->warning("Handler exceeded timeout: {$event}", 'EVENTBUS', [
                        'id' => $id,
                        'duration' => $duration,
                        'timeout' => $timeout
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Handler failed for event: {$event}", 'EVENTBUS', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
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