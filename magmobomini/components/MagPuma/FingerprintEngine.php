<?php

namespace Components\MagPuma;

use MoBo\Logger;
use Components\MagGate\Request;

class FingerprintEngine
{
    private ?Logger $logger;
    
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }
    
    public function generate(Request $request): string
    {
        $components = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accept_language' => $request->header('Accept-Language', ''),
            'accept_encoding' => $request->header('Accept-Encoding', ''),
            'accept' => $request->header('Accept', ''),
            'connection' => $request->header('Connection', ''),
            'header_order' => $this->getHeaderOrder($request),
        ];
        
        $fingerprint = hash('sha256', json_encode($components));
        
        if ($this->logger) {
            $this->logger->debug("Fingerprint generated", 'MAGPUMA', [
                'ip' => $request->ip(),
                'fingerprint' => substr($fingerprint, 0, 16) . '...',
            ]);
        }
        
        return $fingerprint;
    }
    
    private function getHeaderOrder(Request $request): array
    {
        return array_keys($request->headers());
    }
    
    public function isSuspicious(Request $request): bool
    {
        // Check for inconsistencies
        
        // 1. User Agent vs Accept Headers
        if ($this->userAgentMismatch($request)) {
            return true;
        }
        
        // 2. Missing common headers
        if ($this->missingCommonHeaders($request)) {
            return true;
        }
        
        return false;
    }
    
    private function userAgentMismatch(Request $request): bool
    {
        $ua = $request->userAgent();
        $accept = $request->header('Accept', '');
        
        // Chrome should accept webp
        if (str_contains($ua, 'Chrome') && !str_contains($accept, 'webp')) {
            return true;
        }
        
        return false;
    }
    
    private function missingCommonHeaders(Request $request): bool
    {
        $required = ['Accept', 'Accept-Language', 'Accept-Encoding'];
        
        foreach ($required as $header) {
            if (empty($request->header($header))) {
                return true;
            }
        }
        
        return false;
    }
}