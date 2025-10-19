<?php

declare(strict_types=1);

return [
    'health' => [
        'check_interval' => 30,
        'timeout' => 5,
        'retries' => 3,
        'retry_delay' => 5,
        'failure_threshold' => 2,
        'recovery_threshold' => 2,
    ],
];
