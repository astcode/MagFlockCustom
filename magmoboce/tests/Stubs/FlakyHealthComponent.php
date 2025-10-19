<?php

declare(strict_types=1);

namespace Tests\Stubs;

use MoBo\Contracts\ComponentInterface;

/**
 * Component stub whose health oscillates between failure and success to exercise HealthMonitor thresholds.
 */
final class FlakyHealthComponent implements ComponentInterface
{
    private string $name;
    private array $healthSequence;
    private int $index = 0;

    /**
     * @param string   $name
     * @param string[] $healthSequence Sequence of statuses returned on successive health() calls.
     */
    public function __construct(string $name, array $healthSequence)
    {
        $this->name = $name;
        $this->healthSequence = $healthSequence;
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
    }

    public function boot(): void
    {
    }

    public function start(): void
    {
    }

    public function stop(): void
    {
    }

    public function health(): array
    {
        $status = $this->healthSequence[$this->index % count($this->healthSequence)];
        $this->index++;

        if ($status === 'exception') {
            throw new \RuntimeException('Simulated health exception');
        }

        return ['status' => $status];
    }

    public function recover(): bool
    {
        return true;
    }

    public function shutdown(int $timeout = 30): void
    {
    }
}
