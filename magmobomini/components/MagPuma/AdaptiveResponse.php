<?php

namespace Components\MagPuma;

use MoBo\Logger;
use Components\MagGate\Request;

class AdaptiveResponse
{
    private ?Logger $logger;
    
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }
    
    public function decide(IPProfile $profile, Request $request): ResponseAction
    {
        $trustScore = $profile->trustScore;
        
        // CRITICAL THREAT (0-20)
        if ($trustScore <= 20 || $profile->isBlacklisted) {
            return new ResponseAction('block', [
                'reason' => 'Critical threat detected',
                'log' => true,
                'notify' => true,
            ]);
        }
        
        // HIGH RISK (21-40)
        if ($trustScore <= 40) {
            return new ResponseAction('challenge', [
                'type' => 'captcha',
                'difficulty' => 'hard',
                'log' => true,
            ]);
        }
        
        // MEDIUM RISK (41-60)
        if ($trustScore <= 60) {
            return new ResponseAction('throttle', [
                'rate' => '10/minute',
                'log' => true,
            ]);
        }
        
        // LOW RISK (61-80)
        if ($trustScore <= 80) {
            return new ResponseAction('monitor', [
                'rate' => '100/minute',
                'log' => false,
            ]);
        }
        
        // TRUSTED (81-100)
        return new ResponseAction('allow', [
            'rate' => '1000/minute',
            'log' => false,
        ]);
    }
}