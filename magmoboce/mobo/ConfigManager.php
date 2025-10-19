<?php

namespace MoBo;

use MoBo\Config\ConfigRedactor;
use MoBo\Config\ConfigSchemaValidator;

class ConfigManager
{
    /**
     * @var array<string, mixed>
     */
    private array $config = [];
    /**
     * @var array<string, mixed>
     */
    private array $previousConfig = [];
    /**
     * @var array<int, string>
     */
    private array $sources = [];
    private ?ConfigSchemaValidator $validator = null;
    private ?ConfigRedactor $redactor = null;
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function setValidator(ConfigSchemaValidator $validator): void
    {
        $this->validator = $validator;
    }

    public function setRedactor(ConfigRedactor $redactor): void
    {
        $this->redactor = $redactor;
    }

    /**
     * Replace configuration entirely with the provided array.
     *
     * @param array<string, mixed> $config
     * @param array<int, string> $sources
     */
    public function replace(array $config, array $sources = []): void
    {
        $this->previousConfig = $this->config;
        $this->config = $config;
        $this->sources = $sources;
        $this->logger->debug('Config replaced', 'CONFIG', ['sources' => $sources]);
    }

    public function rollback(): void
    {
        if ($this->previousConfig === []) {
            return;
        }

        $this->config = $this->previousConfig;
        $this->logger->warning('Rolled back to previous config snapshot', 'CONFIG');
    }

    public function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new \RuntimeException("Config file must return an array: {$path}");
        }

        $this->merge($config, [$path]);
    }

    /**
     * Merge a configuration fragment into the current config.
     *
     * @param array<string, mixed> $config
     * @param array<int, string> $sources
     */
    public function merge(array $config, array $sources = []): void
    {
        $this->config = $this->mergeRecursive($this->config, $config);
        $this->sources = array_merge($this->sources, $sources);
        $this->logger->debug('Config merged', 'CONFIG', ['sources' => $sources]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }

            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $cursor = &$this->config;

        foreach ($keys as $k) {
            if (!is_array($cursor)) {
                $cursor = [];
            }

            if (!array_key_exists($k, $cursor)) {
                $cursor[$k] = [];
            }

            $cursor = &$cursor[$k];
        }

        $cursor = $value;
    }

    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    public function previous(): array
    {
        return $this->previousConfig;
    }

    /**
     * @return array<int, string>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    public function validate(): bool
    {
        if ($this->validator === null) {
            $required = ['kernel.name', 'kernel.version', 'logging.path'];
            foreach ($required as $key) {
                if (!$this->has($key)) {
                    $this->logger->error("Missing required config: {$key}", 'CONFIG');
                    return false;
                }
            }

            return true;
        }

        $errors = $this->validator->validate($this->config);
        if ($errors === []) {
            return true;
        }

        foreach ($errors as $error) {
            $this->logger->error($error, 'CONFIG');
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function redact(array $context): array
    {
        if ($this->redactor === null) {
            return $context;
        }

        return $this->redactor->redact($context);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && array_key_exists($key, $base) && is_array($base[$key])) {
                $base[$key] = $this->mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
