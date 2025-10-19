<?php

declare(strict_types=1);

namespace MoBo\MagMigrate;

final class MagMigrateContext
{
    public function __construct(
        private readonly string $component,
        private readonly string $connection,
        private readonly MagMigrate $service
    ) {
    }

    public function component(): string
    {
        return $this->component;
    }

    public function connection(): string
    {
        return $this->connection;
    }

    public function service(): MagMigrate
    {
        return $this->service;
    }
}
