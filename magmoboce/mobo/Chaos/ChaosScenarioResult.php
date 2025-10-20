<?php

declare(strict_types=1);

namespace MoBo\Chaos;

final class ChaosScenarioResult
{
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public function __construct(
        private readonly string $name,
        private readonly string $status,
        private readonly float $durationMs,
        private readonly string $message,
        private readonly array $details = []
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'duration_ms' => $this->durationMs,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}
