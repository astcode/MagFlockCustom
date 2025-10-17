<?php

namespace Components\MagPuma;

use MoBo\Logger;
use Components\MagGate\Request;

class ThreatAnalyzer
{
    private ?Logger $logger;
    
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }
    
    public function analyze(Request $request): ThreatScore
    {
        $score = new ThreatScore();
        
        // 1. SQL Injection Detection
        $score->sqlInjection = $this->detectSQLInjection($request);
        
        // 2. XSS Detection
        $score->xss = $this->detectXSS($request);
        
        // 3. Path Traversal Detection
        $score->pathTraversal = $this->detectPathTraversal($request);
        
        // 4. Command Injection Detection
        $score->commandInjection = $this->detectCommandInjection($request);
        
        // 5. Suspicious Headers
        $score->suspiciousHeaders = $this->detectSuspiciousHeaders($request);
        
        // 6. Malformed Requests
        $score->malformed = $this->detectMalformed($request);
        
        // Calculate overall threat level
        $score->calculateThreatLevel();
        
        if ($this->logger && $score->threatLevel > 0) {
            $this->logger->warning("Threat detected", 'MAGPUMA', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'threat_level' => $score->threatLevel,
                'threats' => $score->getThreats(),
            ]);
        }
        
        return $score;
    }
    
    private function detectSQLInjection(Request $request): int
    {
        $patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\'|\")(\s)*(OR|AND)(\s)*(\d+)(\s)*(=|>|<)(\s)*(\d+)/i',
            '/(\'|\")(\s)*(OR|AND)(\s)*(\'|\")(\s)*(=|>|<)(\s)*(\'|\")/i',
            '/(\-\-|#|\/\*|\*\/)/i',
        ];
        
        $inputs = array_merge(
            $request->query(),
            $request->post(),
            $request->route()
        );
        
        $score = 0;
        
        foreach ($inputs as $value) {
            if (!is_string($value)) continue;
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $score += 30;
                }
            }
        }
        
        return min(100, $score);
    }
    
    private function detectXSS(Request $request): int
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i', // onclick, onload, etc.
            '/<embed[^>]*>/i',
            '/<object[^>]*>/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i',
        ];
        
        $inputs = array_merge(
            $request->query(),
            $request->post(),
            $request->route()
        );
        
        $score = 0;
        
        foreach ($inputs as $value) {
            if (!is_string($value)) continue;
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $score += 30;
                }
            }
        }
        
        return min(100, $score);
    }
    
    private function detectPathTraversal(Request $request): int
    {
        $patterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/i',
            '/%2e%2e\//',
            '/\.\.%2f/i',
        ];
        
        $path = $request->path();
        $inputs = array_merge($request->query(), $request->post());
        
        $score = 0;
        
        // Check path
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path)) {
                $score += 40;
            }
        }
        
        // Check inputs
        foreach ($inputs as $value) {
            if (!is_string($value)) continue;
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $score += 30;
                }
            }
        }
        
        return min(100, $score);
    }
    
    private function detectCommandInjection(Request $request): int
    {
        $patterns = [
            '/;.*\b(ls|cat|wget|curl|nc|bash|sh|cmd|powershell)\b/i',
            '/\|.*\b(ls|cat|wget|curl|nc|bash|sh|cmd|powershell)\b/i',
            '/`.*`/',
            '/\$\(.*\)/',
        ];
        
        $inputs = array_merge(
            $request->query(),
            $request->post(),
            $request->route()
        );
        
        $score = 0;
        
        foreach ($inputs as $value) {
            if (!is_string($value)) continue;
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $score += 40;
                }
            }
        }
        
        return min(100, $score);
    }
    
    private function detectSuspiciousHeaders(Request $request): int
    {
        $score = 0;
        
        // Check for missing User-Agent
        if (empty($request->userAgent())) {
            $score += 20;
        }
        
        // Check for suspicious User-Agents
        $suspiciousUA = [
            'sqlmap',
            'nikto',
            'nmap',
            'masscan',
            'metasploit',
            'burp',
            'zap',
            'acunetix',
        ];
        
        $ua = strtolower($request->userAgent());
        foreach ($suspiciousUA as $tool) {
            if (str_contains($ua, $tool)) {
                $score += 50;
            }
        }
        
        // Check for header injection attempts
        $headers = $request->headers();
        foreach ($headers as $value) {
            if (preg_match('/[\r\n]/', $value)) {
                $score += 40;
            }
        }
        
        return min(100, $score);
    }
    
    private function detectMalformed(Request $request): int
    {
        $score = 0;
        
        // Check for excessively long URLs
        if (strlen($request->fullUrl()) > 2000) {
            $score += 20;
        }
        
        // Check for too many query parameters
        if (count($request->query()) > 50) {
            $score += 30;
        }
        
        // Check for null bytes
        $inputs = array_merge(
            $request->query(),
            $request->post()
        );
        
        foreach ($inputs as $value) {
            if (is_string($value) && str_contains($value, "\0")) {
                $score += 50;
            }
        }
        
        return min(100, $score);
    }
}