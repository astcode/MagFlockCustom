<?php

namespace MoBo;

class ConfigManager
{
    private array $config = [];
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
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

        $this->config = array_merge($this->config, $config);
        $this->logger->debug("Config loaded: {$path}", 'CONFIG');
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function validate(): bool
    {
        $required = ['kernel.name', 'kernel.version', 'logging.path'];
        
        foreach ($required as $key) {
            if (!$this->has($key)) {
                $this->logger->error("Missing required config: {$key}", 'CONFIG');
                return false;
            }
        }

        return true;
    }
}