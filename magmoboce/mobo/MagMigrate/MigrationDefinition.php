<?php

declare(strict_types=1);

namespace MoBo\MagMigrate;

final class MigrationDefinition
{
    /**
     * @param list<string> $up
     * @param list<string> $down
     */
    public function __construct(
        private readonly string $component,
        private readonly string $id,
        private readonly string $description,
        private readonly array $up,
        private readonly array $down
    ) {
    }

    public function component(): string
    {
        return $this->component;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return list<string>
     */
    public function upStatements(): array
    {
        return $this->up;
    }

    /**
     * @return list<string>
     */
    public function downStatements(): array
    {
        return $this->down;
    }

    public function checksum(): string
    {
        return hash('sha256', $this->id . '|' . json_encode([$this->up, $this->down], JSON_THROW_ON_ERROR));
    }
}
