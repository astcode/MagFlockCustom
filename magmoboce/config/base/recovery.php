<?php

declare(strict_types=1);

return [
    'recovery' => [
        'max_restarts' => 3,
        'restart_delay' => 10,
        'backoff' => 'exponential',
    ],
];
