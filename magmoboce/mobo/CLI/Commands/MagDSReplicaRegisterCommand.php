<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use Components\MagDB\MagDB;
use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\Kernel;
use RuntimeException;

final class MagDSReplicaRegisterCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('magds:replica-register', 'Register or update a MagDS replica definition');
    }

    /**
     * @param list<string> $args
     */
    public function execute(array $args): int
    {
        $parsed = Arguments::parse($args);
        $options = $parsed['options'];

        $connection = $options['connection'] ?? null;
        if ($connection === null || $connection === '') {
            $this->error('Replica registration requires --connection=<name>.');
            return 1;
        }

        $definition = ['connection' => (string) $connection];

        if (isset($options['priority'])) {
            $definition['priority'] = (int) $options['priority'];
        }

        if (isset($options['weight'])) {
            $definition['weight'] = (int) $options['weight'];
        }

        if (isset($options['lag-threshold'])) {
            $definition['lag_threshold_seconds'] = (int) $options['lag-threshold'];
        }

        if (isset($options['read-only'])) {
            $definition['read_only'] = $this->coerceBool($options['read-only']);
        }

        if (isset($options['auto-promote'])) {
            $definition['auto_promote'] = $this->coerceBool($options['auto-promote']);
        }

        if (isset($options['tags'])) {
            $definition['tags'] = $this->parseTags((string) $options['tags']);
        }

        $kernel = Kernel::getInstance();
        $component = $kernel->get('MagDB');
        if (!$component instanceof MagDB) {
            $this->error('MagDB component not registered.');
            return 1;
        }

        try {
            $component->registerReplica($definition);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return 1;
        }

        $this->writeln("Replica {$connection} registered.");
        return 0;
    }

    private function coerceBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower((string) $value);
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<string, string>
     */
    private function parseTags(string $input): array
    {
        $tags = [];
        foreach (explode(',', $input) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            if (!str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key !== '') {
                $tags[$key] = $value;
            }
        }

        return $tags;
    }
}
