<?php
namespace MoBo\Contracts;

use MoBo\EventBus;
use MoBo\Logger;
use MoBo\Registry;

abstract class AbstractComponent implements ComponentInterface {
    protected string $name;
    protected array $config;
    protected Registry $registry;
    protected EventBus $events;
    protected Logger $logger;
    protected string $state = 'registered';

    public function __construct(string $name, array $config, Registry $registry, EventBus $events, Logger $logger) {
        $this->name = $name;
        $this->config = $config;
        $this->registry = $registry;
        $this->events = $events;
        $this->logger = $logger;
    }

    public function name(): string { return $this->name; }
    public function state(): string { return $this->state; }

    public function boot(): void {
        $this->state = 'loaded';
    }

    public function start(): void {
        $this->state = 'running';
        $this->events->emit('component.started', ['name' => $this->name]);
    }

    public function stop(): void {
        $this->state = 'stopped';
        $this->events->emit('component.stopped', ['name' => $this->name]);
    }
}
