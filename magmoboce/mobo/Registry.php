<?php

namespace MoBo;

use MoBo\Contracts\ComponentInterface;

class Registry
{
    private array $components = [];
    private Logger $logger;
    private EventBus $eventBus;
    private ?Telemetry $telemetry;

    public function __construct(Logger $logger, EventBus $eventBus, ?Telemetry $telemetry = null)
    {
        $this->logger = $logger;
        $this->eventBus = $eventBus;
        $this->telemetry = $telemetry;
    }

    public function register(ComponentInterface $component): void
    {
        $name = $component->getName();

        if ($this->has($name)) {
            throw new \RuntimeException("Component already registered: {$name}");
        }

        $this->components[$name] = [
            'instance' => $component,
            'name' => $name,
            'version' => $component->getVersion(),
            'dependencies' => $component->getDependencies(),
            'state' => 'registered',
            'health' => 'unknown',
            'last_check' => null,
            'restart_count' => 0,
            'registered_at' => date('c')
        ];

        $this->logger->info("Component registered: {$name}", 'REGISTRY', [
            'version' => $component->getVersion(),
            'dependencies' => $component->getDependencies()
        ]);

        $this->eventBus->emit('component.registered', ['name' => $name]);
    }

    public function get(string $name): ?ComponentInterface
    {
        return $this->components[$name]['instance'] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->components[$name]);
    }

    public function list(): array
    {
        return array_map(function($component) {
            return [
                'name' => $component['name'],
                'version' => $component['version'],
                'state' => $component['state'],
                'health' => $component['health'],
                'dependencies' => $component['dependencies']
            ];
        }, $this->components);
    }

    public function getState(string $name): string
    {
        return $this->components[$name]['state'] ?? 'unknown';
    }

    public function setState(string $name, string $state): void
    {
        if (!$this->has($name)) {
            throw new \RuntimeException("Component not found: {$name}");
        }

        $oldState = $this->components[$name]['state'];
        $this->components[$name]['state'] = $state;

        $this->logger->info("Component state changed: {$name}", 'REGISTRY', [
            'old_state' => $oldState,
            'new_state' => $state
        ]);

        $this->eventBus->emit('component.state_changed', [
            'name' => $name,
            'old_state' => $oldState,
            'new_state' => $state
        ]);
        $this->telemetry?->incrementCounter('component.state_changes_total', 1, [
            'component' => $name,
            'state' => $state,
        ]);
    }

    public function setHealth(string $name, string $health, array $details = []): void
    {
        if (!$this->has($name)) {
            return;
        }

        $this->components[$name]['health'] = $health;
        $this->components[$name]['health_details'] = $details;
        $this->components[$name]['last_check'] = date('c');
    }

    public function getHealth(string $name): array
    {
        if (!$this->has($name)) {
            return ['status' => 'unknown'];
        }

        return [
            'status' => $this->components[$name]['health'],
            'details' => $this->components[$name]['health_details'] ?? [],
            'last_check' => $this->components[$name]['last_check']
        ];
    }

    public function getDependencies(string $name): array
    {
        return $this->components[$name]['dependencies'] ?? [];
    }

    public function resolveDependencies(): array
    {
        $resolved = [];
        $unresolved = array_keys($this->components);

        while (!empty($unresolved)) {
            $progress = false;

            foreach ($unresolved as $key => $name) {
                $dependencies = $this->getDependencies($name);
                $canResolve = true;

                foreach ($dependencies as $dep) {
                    if (!in_array($dep, $resolved)) {
                        $canResolve = false;
                        break;
                    }
                }

                if ($canResolve) {
                    $resolved[] = $name;
                    unset($unresolved[$key]);
                    $progress = true;
                }
            }

            if (!$progress) {
                throw new \RuntimeException("Circular dependency detected: " . implode(', ', $unresolved));
            }
        }

        $this->logger->info("Dependencies resolved", 'REGISTRY', ['order' => $resolved]);

        return $resolved;
    }

    public function incrementRestartCount(string $name): int
    {
        if (!$this->has($name)) {
            return 0;
        }

        $this->components[$name]['restart_count']++;
        $this->telemetry?->incrementCounter('component.restarts_total', 1, [
            'component' => $name,
        ]);
        return $this->components[$name]['restart_count'];
    }

    public function resetRestartCount(string $name): void
    {
        if ($this->has($name)) {
            $this->components[$name]['restart_count'] = 0;
        }
    }

    public function getRestartCount(string $name): int
    {
        return $this->components[$name]['restart_count'] ?? 0;
    }

    public function all(): array
    {
        return $this->components;
    }
}
