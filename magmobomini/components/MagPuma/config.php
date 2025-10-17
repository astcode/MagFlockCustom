<?php

return [
    'enabled' => true,
    
    'ip_intelligence' => [
        'cache_ttl' => 3600, // 1 hour
        'trust_threshold' => 60,
    ],
    
    'threat_detection' => [
        'sql_injection' => true,
        'xss' => true,
        'path_traversal' => true,
        'command_injection' => true,
    ],
    
    'bot_detection' => [
        'allow_good_bots' => true,
        'block_bad_bots' => true,
        'confidence_threshold' => 70,
    ],
    
    'ddos_protection' => [
        'enabled' => true,
        'rate_limit' => 100, // requests per minute
        'burst_limit' => 200,
    ],
    
    'rate_limits' => [
        'trusted' => 1000,    // 81-100 trust score
        'normal' => 100,      // 61-80 trust score
        'suspicious' => 10,   // 41-60 trust score
        'critical' => 0,      // 0-40 trust score (block)
    ],
];