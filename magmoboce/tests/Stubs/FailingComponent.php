<?php

declare(strict_types=1);

namespace Tests\Stubs;

use MoBo\Contracts\ComponentInterface;

/**
 * Component stub that fails during lifecycle hooks to exercise error handling paths.
 */
final class FailingComponent implements ComponentInterface
{
    private string $name;
    private string $failStage;

    /**
     * @param string $name
     * @param string $failStage lifecycle stage to throw (configure|boot|start|stop|shutdown)
     */
    public function __construct(string $name, string $failStage)
    {
        $this->name = $name;
        $this->failStage = $failStage;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return '0.1.0';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function configure(array $config): void
    {
        $this->throwIf('configure');
    }

    public function boot(): void
    {
        $this->throwIf('boot');
    }

    public function start(): void
    {
        $this->throwIf('start');
    }

    public function stop(): void
    {
        $this->throwIf('stop');
    }

    public function health(): array
    {
        return ['status' => 'failed'];
    }

    public function recover(): bool
    {
        return false;
    }

    public function shutdown(int $timeout = 30): void
    {
        $this->throwIf('shutdown');
    }

    private function throwIf(string $stage): void
    {
        if ($this->failStage === $stage) {
            throw new \RuntimeException("Intentionally failing during {$stage}");
        }
    }
}
