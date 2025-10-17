<?php

namespace Components\MagPuma;

use Components\MagDB\MagDB;
use MoBo\Logger;
use Components\MagGate\Request;

class DDoSDetector
{
    private ?MagDB $db;
    private ?Logger $logger;
    private array $requestCounts = [];
    
    public function __construct(?MagDB $db = null, ?Logger $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    public function detect(Request $request): bool
    {
        $ip = $request->ip();
        
        // Check in-memory rate limiting (for current process)
        if ($this->isRateLimitExceeded($ip)) {
            if ($this->logger) {
                $this->logger->warning("Rate limit exceeded", 'MAGPUMA', [
                    'ip' => $ip,
                    'path' => $request->path(),
                ]);
            }
            return true;
        }
        
        // Check database for distributed rate limiting
        if ($this->db && $this->isDDoSPattern($ip)) {
            if ($this->logger) {
                $this->logger->warning("DDoS pattern detected", 'MAGPUMA', [
                    'ip' => $ip,
                ]);
            }
            return true;
        }
        
        // Track this request
        $this->trackRequest($ip);
        
        return false;
    }
    
    private function isRateLimitExceeded(string $ip): bool
    {
        $now = time();
        $window = 60; // 1 minute
        $limit = 100; // 100 requests per minute
        
        // Clean old entries
        if (isset($this->requestCounts[$ip])) {
            $this->requestCounts[$ip] = array_filter(
                $this->requestCounts[$ip],
                fn($timestamp) => $timestamp > ($now - $window)
            );
        }
        
        // Check count
        $count = count($this->requestCounts[$ip] ?? []);
        
        return $count >= $limit;
    }
    
    private function isDDoSPattern(string $ip): bool
    {
        try {
            // Check requests in last minute
            $result = $this->db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM mag_request_logs 
                 WHERE ip = ? 
                 AND created_at > NOW() - INTERVAL '1 minute'",
                [$ip],
                'magds'
            );
            
            if ($result && $result['count'] > 100) {
                return true;
            }
            
            // Check for distributed attack (many IPs hitting same endpoint)
            $result = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT ip) as unique_ips 
                 FROM mag_request_logs 
                 WHERE created_at > NOW() - INTERVAL '1 minute'",
                [],
                'magds'
            );
            
            if ($result && $result['unique_ips'] > 1000) {
                return true;
            }
            
        } catch (\Exception $e) {
            // Table might not exist yet
            return false;
        }
        
        return false;
    }
    
    private function trackRequest(string $ip): void
    {
        $now = time();
        
        if (!isset($this->requestCounts[$ip])) {
            $this->requestCounts[$ip] = [];
        }
        
        $this->requestCounts[$ip][] = $now;
    }
}