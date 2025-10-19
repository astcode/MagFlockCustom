<?php

declare(strict_types=1);

return [
    'kernel' => [
        'name' => 'MoBoCE',
        'version' => '1.0.0',
        'environment' => getenv('MOBO_ENV') ?: 'development',
        'configured' => true,
    ],
];
