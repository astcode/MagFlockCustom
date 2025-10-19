<?php

declare(strict_types=1);

return [
    'urls' => [
        'app' => getenv('APP_URL') ?: 'http://magflockcustom.test/magmoboce',
        'mobo' => getenv('MOBO_URL') ?: 'http://magflockcustom.test/magmoboce',
    ],
];
