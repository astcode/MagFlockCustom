<?php

namespace Components\MagPuma;

use Components\MagDB\MagDB;
use MoBo\Logger;

class IPIntelligence
{
    private ?MagDB $db;
    private ?Logger $logger;
    private array $cache = [];
    
    public function __construct(?MagDB $db = null, ?Logger $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    public function analyze(string $ip): IPProfile
    {
        // Check cache first
        if (isset($this->cache[$ip])) {
            return $this->cache[$ip];
        }
        
        $profile = new IPProfile($ip);
        
        // 1. IP TYPE DETECTION
        $profile->type = $this->detectIPType($ip);
        
        // 2. GEOLOCATION (simplified for MVP)
        $profile->geo = $this->getGeolocation($ip);
        
        // 3. REPUTATION CHECK
        $profile->reputation = $this->checkReputation($ip);
        
        // 4. HISTORICAL BEHAVIOR
        if ($this->db) {
            $profile->history = $this->getHistoricalBehavior($ip);
        }
        
        // 5. PROXY/VPN DETECTION
        $profile->isProxy = $this->isProxyOrVPN($ip);
        
        // 6. BLACKLIST CHECK
        $profile->isBlacklisted = $this->isBlacklisted($ip);
        
        // 7. CALCULATE TRUST SCORE
        $profile->trustScore = $this->calculateTrustScore($profile);
        
        // Cache it
        $this->cache[$ip] = $profile;
        
        if ($this->logger) {
            $this->logger->debug("IP analyzed", 'MAGPUMA', [
                'ip' => $ip,
                'type' => $profile->type,
                'trust_score' => $profile->trustScore,
            ]);
        }
        
        return $profile;
    }
    
    private function detectIPType(string $ip): string
    {
        // Check for private/local IPs
        if ($this->isPrivateIP($ip)) {
            return 'private';
        }
        
        // Check for known datacenter ranges
        $datacenters = [
            'aws' => [
                ['54.0.0.0', '54.255.255.255'],
                ['52.0.0.0', '52.255.255.255'],
            ],
            'gcp' => [
                ['35.0.0.0', '35.255.255.255'],
                ['34.0.0.0', '34.255.255.255'],
            ],
            'azure' => [
                ['13.0.0.0', '13.255.255.255'],
                ['40.0.0.0', '40.255.255.255'],
            ],
            'digitalocean' => [
                ['159.65.0.0', '159.65.255.255'],
                ['167.99.0.0', '167.99.255.255'],
            ],
        ];
        
        $ipLong = ip2long($ip);
        
        foreach ($datacenters as $provider => $ranges) {
            foreach ($ranges as $range) {
                $start = ip2long($range[0]);
                $end = ip2long($range[1]);
                
                if ($ipLong >= $start && $ipLong <= $end) {
                    return "datacenter:{$provider}";
                }
            }
        }
        
        return 'residential';
    }
    
    private function isPrivateIP(string $ip): bool
    {
        $private_ranges = [
            ['10.0.0.0', '10.255.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['127.0.0.0', '127.255.255.255'],
        ];
        
        $ipLong = ip2long($ip);
        
        foreach ($private_ranges as $range) {
            $start = ip2long($range[0]);
            $end = ip2long($range[1]);
            
            if ($ipLong >= $start && $ipLong <= $end) {
                return true;
            }
        }
        
        return false;
    }
    
    private function getGeolocation(string $ip): array
    {
        // Simplified for MVP - would use MaxMind GeoIP2 in production
        return [
            'country' => 'Unknown',
            'city' => 'Unknown',
            'isp' => 'Unknown',
        ];
    }
    
    private function checkReputation(string $ip): array
    {
        $reputation = [
            'score' => 100,
            'threats' => [],
            'sources' => [],
        ];
        
        // Check local threat database
        if ($this->db) {
            try {
                $threats = $this->db->query(
                    "SELECT COUNT(*) as count FROM mag_threats 
                     WHERE ip = ? AND expires_at > NOW()",
                    [$ip],
                    'magds'
                );
                
                if (!empty($threats) && $threats[0]['count'] > 0) {
                    $reputation['score'] -= 50;
                    $reputation['threats'][] = 'local_database';
                }
            } catch (\Exception $e) {
                // Silently fail - table might not exist yet
            }
        }
        
        return $reputation;
    }
    
    private function getHistoricalBehavior(string $ip): array
    {
        try {
            $stats = $this->db->fetchOne(
                "SELECT 
                    COUNT(*) as total_requests,
                    COUNT(DISTINCT endpoint) as unique_endpoints,
                    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors,
                    SUM(CASE WHEN blocked = true THEN 1 ELSE 0 END) as blocks,
                    MIN(created_at) as first_seen,
                    MAX(created_at) as last_seen
                FROM mag_request_logs
                WHERE ip = ?
                AND created_at > NOW() - INTERVAL '30 days'",
                [$ip],
                'magds'
            );
            
            return $stats ?: $this->getDefaultHistory();
        } catch (\Exception $e) {
            return $this->getDefaultHistory();
        }
    }
    
    private function getDefaultHistory(): array
    {
        return [
            'total_requests' => 0,
            'unique_endpoints' => 0,
            'errors' => 0,
            'blocks' => 0,
            'first_seen' => null,
            'last_seen' => null,
        ];
    }
    
    private function isProxyOrVPN(string $ip): bool
    {
        // Simplified for MVP - would use IPHub or similar in production
        return false;
    }
    
    private function isBlacklisted(string $ip): bool
    {
        // Check local blacklist
        if ($this->db) {
            try {
                $result = $this->db->fetchOne(
                    "SELECT COUNT(*) as count FROM mag_blacklist 
                     WHERE ip = ? AND (expires_at IS NULL OR expires_at > NOW())",
                    [$ip],
                    'magds'
                );
                
                return !empty($result) && $result['count'] > 0;
            } catch (\Exception $e) {
                return false;
            }
        }
        
        return false;
    }
    
    private function calculateTrustScore(IPProfile $profile): int
    {
        $score = 100;
        
        // Reputation penalty
        $score = min($score, $profile->reputation['score']);
        
        // IP Type penalties
        if (str_starts_with($profile->type, 'datacenter:')) {
            $score -= 20;
        }
        
        // Proxy penalty
        if ($profile->isProxy) {
            $score -= 25;
        }
        
        // Blacklist penalty
        if ($profile->isBlacklisted) {
            $score -= 80;
        }
        
        // Historical behavior
        if (isset($profile->history['total_requests']) && $profile->history['total_requests'] > 0) {
            $errorRate = $profile->history['errors'] / $profile->history['total_requests'];
            $blockRate = $profile->history['blocks'] / $profile->history['total_requests'];
            
            if ($errorRate > 0.5) {
                $score -= 30;
            }
            
            if ($blockRate > 0.1) {
                $score -= 40;
            }
            
            // Good behavior bonus
            if ($errorRate < 0.05 && $blockRate == 0 && $profile->history['total_requests'] > 100) {
                $score += 10;
            }
        }
        
        return max(0, min(100, $score));
    }
}