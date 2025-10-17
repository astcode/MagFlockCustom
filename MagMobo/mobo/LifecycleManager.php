<?php

namespace MoBo;

class LifecycleManager
{
    private Registry $registry;
    private Logger $logger;
    private EventBus $eventBus;
    private ConfigManager $config;

    public function __construct(Registry $registry, Logger $logger, EventBus $eventBus, ConfigManager $config)
    {
        $this->registry = $registry;
        $this->logger = $logger;
        $this->eventBus = $eventBus;
        $this->config = $config;
    }

    public function start(string $name): bool
    {
        $component = $this->registry->get($name);

        if (!$component) {
            $this->logger->error("Cannot start component: not found", 'LIFECYCLE', ['name' => $name]);
            return false;
        }

        $currentState = $this->registry->getState($name);

        if ($currentState === 'running') {
            $this->logger->warning("Component already running: {$name}", 'LIFECYCLE');
            return true;
        }

        try {
            $this->logger->info("Starting component: {$name}", 'LIFECYCLE');
            $this->registry->setState($name, 'starting');

            $component->start();

            $this->registry->setState($name, 'running');
            $this->registry->resetRestartCount($name);

            $this->eventBus->emit('component.started', ['name' => $name]);

            $this->logger->info("Component started: {$name}", 'LIFECYCLE');

            return true;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to start component: {$name}", 'LIFECYCLE', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->registry->setState($name, 'failed');
            $this->eventBus->emit('component.failed', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function stop(string $name): bool
    {
        $component = $this->registry->get($name);

        if (!$component) {
            $this->logger->error("Cannot stop component: not found", 'LIFECYCLE', ['name' => $name]);
            return false;
        }

        $currentState = $this->registry->getState($name);

        if ($currentState === 'stopped') {
            $this->logger->warning("Component already stopped: {$name}", 'LIFECYCLE');
            return true;
        }

        try {
            $this->logger->info("Stopping component: {$name}", 'LIFECYCLE');
            $this->registry->setState($name, 'stopping');

            $component->stop();

            $this->registry->setState($name, 'stopped');

            $this->eventBus->emit('component.stopped', ['name' => $name]);

            $this->logger->info("Component stopped: {$name}", 'LIFECYCLE');

            return true;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to stop component: {$name}", 'LIFECYCLE', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function restart(string $name): bool
    {
        $this->logger->info("Restarting component: {$name}", 'LIFECYCLE');

        if (!$this->stop($name)) {
            return false;
        }

        sleep(2); // Brief pause

        return $this->start($name);
    }

    public function startAll(): bool
    {
        $this->logger->info("Starting all components", 'LIFECYCLE');

        try {
            $order = $this->registry->resolveDependencies();

            foreach ($order as $name) {
                if (!$this->start($name)) {
                    throw new \RuntimeException("Failed to start component: {$name}");
                }
            }

            return true;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to start all components", 'LIFECYCLE', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function stopAll(): bool
    {
        $this->logger->info("Stopping all components", 'LIFECYCLE');

        try {
            $order = array_reverse($this->registry->resolveDependencies());

            foreach ($order as $name) {
                $this->stop($name);
            }

            return true;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to stop all components", 'LIFECYCLE', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function recover(string $name): bool
    {
        $component = $this->registry->get($name);

        if (!$component) {
            return false;
        }

        $maxRestarts = $this->config->get('recovery.max_restarts', 3);
        $restartCount = $this->registry->getRestartCount($name);

        if ($restartCount >= $maxRestarts) {
            $this->logger->critical("Component exceeded max restart attempts: {$name}", 'LIFECYCLE', [
                'restart_count' => $restartCount,
                'max_restarts' => $maxRestarts
            ]);

            $this->eventBus->emit('component.recovery_failed', [
                'name' => $name,
                'restart_count' => $restartCount
            ]);

            return false;
        }

        $this->registry->incrementRestartCount($name);
        $restartDelay = $this->config->get('recovery.restart_delay', 10);
        $backoff = $this->config->get('recovery.backoff', 'exponential');

        if ($backoff === 'exponential') {
            $delay = $restartDelay * pow(2, $restartCount);
        } else {
            $delay = $restartDelay;
        }

        $this->logger->info("Attempting recovery for component: {$name}", 'LIFECYCLE', [
            'attempt' => $restartCount + 1,
            'delay' => $delay
        ]);

        sleep($delay);

        try {
            if ($component->recover()) {
                $this->logger->info("Component recovered: {$name}", 'LIFECYCLE');
                $this->registry->resetRestartCount($name);
                return $this->start($name);
            }
        } catch (\Throwable $e) {
            $this->logger->error("Recovery failed for component: {$name}", 'LIFECYCLE', [
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    public function shutdown(int $timeout = 30): void
    {
        $this->logger->info("Initiating graceful shutdown", 'LIFECYCLE', ['timeout' => $timeout]);

        $this->eventBus->emit('system.shutdown', ['timeout' => $timeout]);

        $this->stopAll();

        $this->logger->info("Shutdown complete", 'LIFECYCLE');
    }
}