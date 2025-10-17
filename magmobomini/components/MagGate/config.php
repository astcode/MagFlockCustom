<?php

return [
    'middleware' => [
        'global' => [
            'magpuma.pre',
            'cors',
        ],
    ],
    
    'cors' => [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['*'],
        'max_age' => 86400,
    ],
    
    'rate_limit' => [
        'default' => 60, // requests per minute
        'api' => 1000,
    ],
];