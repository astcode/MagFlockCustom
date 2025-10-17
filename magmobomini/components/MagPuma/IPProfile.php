<?php

namespace Components\MagPuma;

class IPProfile
{
    public string $ip;
    public string $type = 'unknown';
    public array $geo = [];
    public array $reputation = [];
    public array $history = [];
    public bool $isProxy = false;
    public bool $isBlacklisted = false;
    public int $trustScore = 50;
    
    public function __construct(string $ip)
    {
        $this->ip = $ip;
    }
    
    public function isTrusted(): bool
    {
        return $this->trustScore >= 80;
    }
    
    public function isSuspicious(): bool
    {
        return $this->trustScore < 60;
    }
    
    public function isCritical(): bool
    {
        return $this->trustScore < 20;
    }
    
    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'type' => $this->type,
            'geo' => $this->geo,
            'reputation' => $this->reputation,
            'history' => $this->history,
            'is_proxy' => $this->isProxy,
            'is_blacklisted' => $this->isBlacklisted,
            'trust_score' => $this->trustScore,
        ];
    }
}