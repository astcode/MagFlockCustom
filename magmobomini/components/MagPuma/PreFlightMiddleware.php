<?php

namespace Components\MagPuma;

use Components\MagGate\Middleware\MiddlewareInterface;
use Components\MagGate\Request;
use Components\MagGate\Response;

class PreFlightMiddleware implements MiddlewareInterface
{
    private MagPuma $puma;
    
    public function __construct()
    {
        // Will be injected by kernel
        global $kernel;
        $this->puma = $kernel->get('MagPuma');
    }
    
    public function handle(Request $request, callable $next): Response
    {
        // 1. Analyze IP
        $ipProfile = $this->puma->analyzeIP($request->ip());
        
        // 2. Detect threats
        $threatScore = $this->puma->analyzeThreat($request);
        
        // 3. Detect bots
        $botScore = $this->puma->detectBot($request);
        
        // 4. Detect DDoS
        $isDDoS = $this->puma->detectDDoS($request);
        
        // 5. Generate fingerprint
        $fingerprint = $this->puma->fingerprint($request);
        
        // Store analysis in request for later use
        $request->magpuma = [
            'ip_profile' => $ipProfile,
            'threat_score' => $threatScore,
            'bot_score' => $botScore,
            'is_ddos' => $isDDoS,
            'fingerprint' => $fingerprint,
        ];
        
        // 6. Make decision
        $action = $this->puma->decide($ipProfile, $request);
        
        // Handle action
        if ($action->shouldBlock()) {
            return new Response([
                'error' => 'Access Denied',
                'reason' => $action->options['reason'] ?? 'Security policy violation',
            ], 403);
        }
        
        if ($action->shouldChallenge()) {
            // TODO: Implement CAPTCHA challenge
            return new Response([
                'error' => 'Challenge Required',
                'type' => $action->options['type'] ?? 'captcha',
            ], 429);
        }
        
        if ($isDDoS) {
            return new Response([
                'error' => 'Too Many Requests',
                'retry_after' => 60,
            ], 429);
        }
        
        // Allow request to continue
        return $next($request);
    }
}