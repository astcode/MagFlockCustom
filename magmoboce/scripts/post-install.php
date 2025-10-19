<?php

declare(strict_types=1);

$directories = [
    __DIR__ . '/../storage/logs',
    __DIR__ . '/../storage/backups',
    __DIR__ . '/../storage/cache',
    __DIR__ . '/../storage/state',
];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}
