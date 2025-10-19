<?php

declare(strict_types=1);

namespace MoBo\CLI;

final class Arguments
{
    /**
     * @param list<string> $input
     * @return array{positionals: list<string>, options: array<string, string|bool>}
     */
    public static function parse(array $input): array
    {
        $positionals = [];
        $options = [];

        $count = count($input);
        for ($i = 0; $i < $count; $i++) {
            $token = $input[$i];

            if (!str_starts_with($token, '--')) {
                $positionals[] = $token;
                continue;
            }

            $token = substr($token, 2);
            if ($token === false) {
                continue;
            }

            if (str_contains($token, '=')) {
                [$key, $value] = explode('=', $token, 2);
                $options[$key] = $value;
                continue;
            }

            $next = $input[$i + 1] ?? null;
            if ($next !== null && !str_starts_with($next, '--')) {
                $options[$token] = $next;
                $i++;
            } else {
                $options[$token] = true;
            }
        }

        return [
            'positionals' => $positionals,
            'options' => $options,
        ];
    }
}
