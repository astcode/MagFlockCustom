<?php

namespace Components\MagPuma;

class ResponseAction
{
    public string $action; // block, challenge, throttle, monitor, allow
    public array $options;
    
    public function __construct(string $action, array $options = [])
    {
        $this->action = $action;
        $this->options = $options;
    }
    
    public function shouldBlock(): bool
    {
        return $this->action === 'block';
    }
    
    public function shouldChallenge(): bool
    {
        return $this->action === 'challenge';
    }
    
    public function shouldThrottle(): bool
    {
        return $this->action === 'throttle';
    }
    
    public function shouldMonitor(): bool
    {
        return $this->action === 'monitor';
    }
    
    public function shouldAllow(): bool
    {
        return $this->action === 'allow';
    }
    
    public function getRateLimit(): ?string
    {
        return $this->options['rate'] ?? null;
    }
    
    public function shouldLog(): bool
    {
        return $this->options['log'] ?? false;
    }
    
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'options' => $this->options,
        ];
    }
}