<?php

namespace MoBo;

class EventSchemaRegistry
{
    /**
     * @var array<string, string[]>
     */
    private array $schemas;

    /**
     * @param array<string, string[]> $schemas
     */
    public function __construct(array $schemas = [])
    {
        $this->schemas = $schemas;
    }

    /**
     * @return string[]
     */
    public function getRequiredFields(string $event): array
    {
        return $this->schemas[$event] ?? [];
    }

    /**
     * @return string[] Missing fields
     */
    public function validate(string $event, array $payload): array
    {
        $required = $this->getRequiredFields($event);
        $missing = [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                $missing[] = $field;
            }
        }

        return $missing;
    }
}
