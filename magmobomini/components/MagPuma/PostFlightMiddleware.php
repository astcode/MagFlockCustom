<?php

namespace Components\MagPuma;

use Components\MagGate\Middleware\MiddlewareInterface;
use Components\MagGate\Request;
use Components\MagGate\Response;
use Components\MagDB\MagDB;

class PostFlightMiddleware implements MiddlewareInterface
{
    private ?MagDB $db = null;
    
    public function __construct()
    {
        // Will be injected by kernel
        global $kernel;
        $this->db = $kernel->get('MagDB');
    }
    
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        
        // Log request (async in production)
        $this->logRequest($request, $response);
        
        // Add security headers
        $response->headers([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
        
        // Add MagPuma signature
        $response->header('X-Protected-By', 'MagPuma/1.0');
        
        return $response;
    }
    
    private function logRequest(Request $request, Response $response): void
    {
        if (!$this->db) return;
        
        try {
            $magpuma = $request->magpuma ?? [];
            
            $this->db->insert('mag_request_logs', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'user_agent' => $request->userAgent(),
                'fingerprint' => $magpuma['fingerprint'] ?? null,
                'trust_score' => $magpuma['ip_profile']->trustScore ?? null,
                'threat_level' => $magpuma['threat_score']->threatLevel ?? null,
                'is_bot' => $magpuma['bot_score']->isBot ?? false,
                'blocked' => $response->isError(),
                'response_time' => $request->getElapsedTime(),
                'created_at' => date('Y-m-d H:i:s'),
            ], 'magds');
            
        } catch (\Exception $e) {
            // Silently fail - table might not exist yet
        }
    }
}