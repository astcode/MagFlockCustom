<?php

declare(strict_types=1);

namespace MoBo\Config;

final class ConfigSchemaValidator
{
    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(private readonly array $schema)
    {
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, string> List of validation error messages
     */
    public function validate(array $config): array
    {
        $errors = [];

        foreach ($this->schema as $key => $definition) {
            $path = $key;
            $exists = array_key_exists($key, $config);

            if (($definition['required'] ?? false) && !$exists) {
                $errors[] = "Missing required configuration key: {$path}";
                continue;
            }

            if (!$exists) {
                continue;
            }

            $this->assertType($config[$key], $definition, $path, $errors);
        }

        return $errors;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $definition
     * @param array<int, string> $errors
     */
    private function assertType(mixed $value, array $definition, string $path, array &$errors): void
    {
        $type = $definition['type'] ?? 'mixed';

        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    $errors[] = "{$path} must be a string";
                }
                break;
            case 'boolean':
                if (!is_bool($value)) {
                    $errors[] = "{$path} must be a boolean";
                }
                break;
            case 'enum':
                if (!in_array($value, $definition['values'] ?? [], true)) {
                    $allowed = implode(', ', $definition['values'] ?? []);
                    $errors[] = "{$path} must be one of [{$allowed}]";
                }
                break;
            case 'array':
                if (!is_array($value)) {
                    $errors[] = "{$path} must be an array";
                    break;
                }

                if (isset($definition['items'])) {
                    foreach ($value as $index => $item) {
                        $this->assertType($item, $definition['items'], "{$path}[{$index}]", $errors);
                    }
                }
                break;
            case 'object':
                if (!is_array($value)) {
                    $errors[] = "{$path} must be an associative array";
                    break;
                }

                if (!isset($definition['children']) || !is_array($definition['children'])) {
                    break;
                }

                foreach ($definition['children'] as $childKey => $childDefinition) {
                    $childPath = "{$path}.{$childKey}";
                    $childExists = array_key_exists($childKey, $value);

                    if (($childDefinition['required'] ?? false) && !$childExists) {
                        $errors[] = "Missing required configuration key: {$childPath}";
                        continue;
                    }

                    if (!$childExists) {
                        continue;
                    }

                    $this->assertType($value[$childKey], $childDefinition, $childPath, $errors);
                }
                break;
        }
    }
}
