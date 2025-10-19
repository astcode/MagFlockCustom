<?php

declare(strict_types=1);

namespace MoBo\Security;

final class CapabilityDeniedException extends \RuntimeException
{
    private string $capability;
    private string $actor;
    /** @var array<string, mixed> */
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $capability, string $actor, array $context = [], string $message = '')
    {
        $this->capability = $capability;
        $this->actor = $actor;
        $this->context = $context;

        parent::__construct($message !== '' ? $message : "Capability '{$capability}' denied for actor '{$actor}'");
    }

    public function getCapability(): string
    {
        return $this->capability;
    }

    public function getActor(): string
    {
        return $this->actor;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
