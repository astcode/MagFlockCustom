<?php

namespace Components\MagPuma;

class ThreatScore
{
    public int $sqlInjection = 0;
    public int $xss = 0;
    public int $pathTraversal = 0;
    public int $commandInjection = 0;
    public int $suspiciousHeaders = 0;
    public int $malformed = 0;
    public int $threatLevel = 0;
    
    public function calculateThreatLevel(): void
    {
        // Weight different threats
        $this->threatLevel = (int) (
            ($this->sqlInjection * 1.2) +
            ($this->xss * 1.0) +
            ($this->pathTraversal * 1.1) +
            ($this->commandInjection * 1.3) +
            ($this->suspiciousHeaders * 0.8) +
            ($this->malformed * 0.7)
        ) / 6;
        
        $this->threatLevel = min(100, $this->threatLevel);
    }
    
    public function isCritical(): bool
    {
        return $this->threatLevel >= 70;
    }
    
    public function isHigh(): bool
    {
        return $this->threatLevel >= 50;
    }
    
    public function isMedium(): bool
    {
        return $this->threatLevel >= 30;
    }
    
    public function isLow(): bool
    {
        return $this->threatLevel > 0 && $this->threatLevel < 30;
    }
    
    public function getThreats(): array
    {
        $threats = [];
        
        if ($this->sqlInjection > 0) $threats[] = 'sql_injection';
        if ($this->xss > 0) $threats[] = 'xss';
        if ($this->pathTraversal > 0) $threats[] = 'path_traversal';
        if ($this->commandInjection > 0) $threats[] = 'command_injection';
        if ($this->suspiciousHeaders > 0) $threats[] = 'suspicious_headers';
        if ($this->malformed > 0) $threats[] = 'malformed';
        
        return $threats;
    }
    
    public function toArray(): array
    {
        return [
            'sql_injection' => $this->sqlInjection,
            'xss' => $this->xss,
            'path_traversal' => $this->pathTraversal,
            'command_injection' => $this->commandInjection,
            'suspicious_headers' => $this->suspiciousHeaders,
            'malformed' => $this->malformed,
            'threat_level' => $this->threatLevel,
            'threats' => $this->getThreats(),
        ];
    }
}