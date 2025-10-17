<?php

namespace Components\MagGate\Middleware;

use Components\MagGate\Request;
use Components\MagGate\Response;

class CORS implements MiddlewareInterface
{
    private array $config = [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 86400,
        'supports_credentials' => false,
    ];
    
    public function handle(Request $request, callable $next): Response
    {
        // Handle preflight
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflight($request);
        }
        
        $response = $next($request);
        
        return $this->addCorsHeaders($request, $response);
    }
    
    private function handlePreflight(Request $request): Response
    {
        $response = new Response('', 204);
        return $this->addCorsHeaders($request, $response);
    }
    
    private function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin', '*');
        
        if ($this->isAllowedOrigin($origin)) {
            $response->header('Access-Control-Allow-Origin', $origin);
        }
        
        $response->header('Access-Control-Allow-Methods', implode(', ', $this->config['allowed_methods']));
        $response->header('Access-Control-Allow-Headers', implode(', ', $this->config['allowed_headers']));
        $response->header('Access-Control-Max-Age', (string) $this->config['max_age']);
        
        if ($this->config['supports_credentials']) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
        
        if (!empty($this->config['exposed_headers'])) {
            $response->header('Access-Control-Expose-Headers', implode(', ', $this->config['exposed_headers']));
        }
        
        return $response;
    }
    
    private function isAllowedOrigin(string $origin): bool
    {
        if (in_array('*', $this->config['allowed_origins'])) {
            return true;
        }
        
        return in_array($origin, $this->config['allowed_origins']);
    }
}