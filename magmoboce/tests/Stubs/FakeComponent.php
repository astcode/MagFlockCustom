<?php

declare(strict_types=1);

namespace Tests\Stubs;

use MoBo\Contracts\ComponentInterface;

/**
 * Simple component stub used by kernel harness tests.
 * Records lifecycle transitions so assertions can introspect behaviour.
 */
final class FakeComponent implements ComponentInterface
{
    private string $name;
    private string $version;
    /**
     * @var string[]
     */
    private array $dependencies;
    private array $config = [];
    /**
     * @var string[]
     */
    public array $lifecycle = [];
    private string $health = 'unknown';
    private bool $isRunning = false;

    /**
     * @param string   $name
     * @param string   $version
     * @param string[] $dependencies
     */
    public function __construct(string $name, string $version = '0.1.0', array $dependencies = [])
    {
        $this->name = $name;
        $this->version = $version;
        $this->dependencies = $dependencies;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function configure(array $config): void
    {
        $this->config = $config;
        $this->lifecycle[] = 'configure';
    }

    public function boot(): void
    {
        $this->lifecycle[] = 'boot';
        $this->health = 'booted';
    }

    public function start(): void
    {
        $this->lifecycle[] = 'start';
        $this->health = 'running';
        $this->isRunning = true;
    }

    public function stop(): void
    {
        $this->lifecycle[] = 'stop';
        $this->health = 'stopped';
        $this->isRunning = false;
    }

    public function health(): array
    {
        return [
            'status' => $this->health,
            'running' => $this->isRunning,
        ];
    }

    public function recover(): bool
    {
        $this->lifecycle[] = 'recover';
        $this->health = 'running';
        $this->isRunning = true;

        return true;
    }

    public function shutdown(int $timeout = 30): void
    {
        $this->lifecycle[] = 'shutdown';
        $this->health = 'shutdown';
        $this->isRunning = false;
    }
}
