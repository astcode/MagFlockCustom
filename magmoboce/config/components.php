<?php

return [
    'MagDB' => [
        'class' => \Components\MagDB\MagDB::class,
        'enabled' => true,
        'config' => 'database',
    ],
    'MagPuma' => [
        'class' => \Components\MagPuma\MagPuma::class,
        'enabled' => true,
        'config' => null,
    ],
    'MagGate' => [
        'class' => \Components\MagGate\MagGate::class,
        'enabled' => true,
        'config' => null,
    ],
    'MagView' => [
        'class' => \Components\MagView\MagView::class,
        'enabled' => true,
        'config' => null,
    ],
];
