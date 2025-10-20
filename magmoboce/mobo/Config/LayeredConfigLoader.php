<?php

declare(strict_types=1);

namespace MoBo\Config;

final class LayeredConfigLoader
{
    private string $configPath;
    private string $environment;
    /**
     * @var array<int, string>
     */
    private array $sources = [];

    public function __construct(string $configPath, string $environment)
    {
        $this->configPath = rtrim($configPath, DIRECTORY_SEPARATOR);
        $this->environment = $environment;
    }

    /**
     * Load configuration layers in order: base -> environment -> secrets.
     *
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $this->sources = [];

        $base = $this->loadLayer($this->configPath . '/base');
        $environment = $this->loadLayer($this->configPath . '/environments/' . $this->environment);
        $sharedSecrets = $this->loadLayer($this->configPath . '/secrets');
        $environmentSecrets = $this->loadLayer($this->configPath . '/secrets/' . $this->environment);

        return $this->mergeLayers($base, $environment, $sharedSecrets, $environmentSecrets);
    }

    /**
     * @return array<int, string>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function getConfigRoot(): string
    {
        return $this->configPath;
    }

    /**
     * Compute the set of changed keys between two configuration arrays.
     *
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $next
     * @return array<int, string>
     */
    public function diffKeys(array $previous, array $next): array
    {
        $changes = [];
        $this->diffRecursive($previous, $next, '', $changes);
        return $changes;
    }

    /**
     * @param array<string, mixed> $layers
     */
    private function mergeLayers(array ...$layers): array
    {
        $merged = [];

        foreach ($layers as $layer) {
            $merged = $this->mergeRecursive($merged, $layer);
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $next
     * @param array<int, string> $changes
     */
    private function diffRecursive(array $previous, array $next, string $prefix, array &$changes): void
    {
        $allKeys = array_unique(array_merge(array_keys($previous), array_keys($next)));

        foreach ($allKeys as $key) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (!array_key_exists($key, $previous)) {
                $changes[] = $path;
                continue;
            }

            if (!array_key_exists($key, $next)) {
                $changes[] = $path;
                continue;
            }

            $prevValue = $previous[$key];
            $nextValue = $next[$key];

            if (is_array($prevValue) && is_array($nextValue)) {
                $this->diffRecursive($prevValue, $nextValue, $path, $changes);
                continue;
            }

            if ($prevValue !== $nextValue) {
                $changes[] = $path;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadLayer(string $path): array
    {
        if (!file_exists($path) && file_exists($path . '.php')) {
            $path .= '.php';
        }

        if (!file_exists($path)) {
            return [];
        }

        if (is_dir($path)) {
            return $this->loadDirectory($path);
        }

        if (is_file($path) && str_ends_with($path, '.php')) {
            $this->sources[] = $path;
            /** @var array<string, mixed> $config */
            $config = require $path;
            return $config;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDirectory(string $directory): array
    {
        $config = [];

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . '/*.php');
        if ($files === false) {
            return [];
        }

        sort($files);

        foreach ($files as $file) {
            $this->sources[] = $file;
            /** @var array<string, mixed> $layer */
            $layer = require $file;
            $config = $this->mergeRecursive($config, $layer);
        }

        return $config;
    }
}
