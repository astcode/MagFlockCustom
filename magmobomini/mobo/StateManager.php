<?php

namespace MoBo;

class StateManager
{
    private string $statePath;
    private array $state = [];
    private Logger $logger;

    public function __construct(string $statePath, Logger $logger)
    {
        $this->statePath = $statePath;
        $this->logger = $logger;
        
        if (!is_dir(dirname($statePath))) {
            mkdir(dirname($statePath), 0755, true);
        }
        
        $this->load();
    }

    public function load(): void
    {
        if (!file_exists($this->statePath)) {
            $this->state = $this->getDefaultState();
            $this->save();
            return;
        }

        $json = file_get_contents($this->statePath);
        $state = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("State file corrupted, using defaults", 'STATE');
            $this->state = $this->getDefaultState();
            return;
        }

        $this->state = $state;
        $this->logger->debug("State loaded", 'STATE');
    }

    public function save(): void
    {
        $temp = $this->statePath . '.tmp';
        
        // Write to temp file
        $json = json_encode($this->state, JSON_PRETTY_PRINT);
        file_put_contents($temp, $json, LOCK_EX);
        
        // Atomic rename
        rename($temp, $this->statePath);
        
        $this->logger->debug("State saved", 'STATE');
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->state;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $state = &$this->state;

        foreach ($keys as $k) {
            if (!isset($state[$k])) {
                $state[$k] = [];
            }
            $state = &$state[$k];
        }

        $state = $value;
        $this->save();
    }

    public function getSystemState(): string
    {
        return $this->get('system.state', 'stopped');
    }

    public function setSystemState(string $state): void
    {
        $this->set('system.state', $state);
        $this->set('system.last_update', date('c'));
    }

    public function getComponentState(string $component): string
    {
        return $this->get("components.{$component}", 'stopped');
    }

    public function setComponentState(string $component, string $state): void
    {
        $this->set("components.{$component}", $state);
    }

    private function getDefaultState(): array
    {
        return [
            'system' => [
                'state' => 'stopped',
                'boot_time' => null,
                'last_update' => date('c')
            ],
            'components' => []
        ];
    }

    public function all(): array
    {
        return $this->state;
    }
}