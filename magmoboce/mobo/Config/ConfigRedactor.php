<?php

declare(strict_types=1);

namespace MoBo\Config;

final class ConfigRedactor
{
    /**
     * @var array<int, array{pattern: string, regex: string}>
     */
    private array $patterns = [];

    /**
     * @param array<int, string> $patterns
     */
    public function __construct(array $patterns)
    {
        foreach ($patterns as $pattern) {
            $regex = $this->compilePattern($pattern);
            $this->patterns[] = ['pattern' => $pattern, 'regex' => $regex];
        }
    }

    /**
     * Redact sensitive values in the provided context array.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function redact(array $context): array
    {
        return $this->redactRecursive($context, '');
    }

    private function redactRecursive(mixed $value, string $path): mixed
    {
        if (!is_array($value)) {
            return $this->maybeRedactScalar($value, $path);
        }

        $redacted = [];
        foreach ($value as $key => $child) {
            $childPath = $path === '' ? (string) $key : $path . '.' . $key;
            $redacted[$key] = $this->redactRecursive($child, $childPath);
        }

        return $this->maybeRedactScalar($redacted, $path);
    }

    private function maybeRedactScalar(mixed $value, string $path): mixed
    {
        if ($path === '') {
            return $value;
        }

        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern['regex'], $path) !== 1) {
                continue;
            }

            if ($pattern['pattern'] === 'database.connections.*.username') {
                if (!is_scalar($value)) {
                    return '[HASHED]';
                }

                return '[HASH:' . substr(hash('sha256', (string) $value), 0, 12) . ']';
            }

            return '[REDACTED]';
        }

        return $value;
    }

    private function compilePattern(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        $escaped = str_replace('\*', '[^.]+', $escaped);

        return '/^' . $escaped . '$/';
    }
}
