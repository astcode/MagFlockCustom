<?php

namespace MoBo;

class CacheManager
{
    private array $cache = [];
    private array $ttl = [];
    private Logger $logger;
    private string $cachePath;

    public function __construct(string $cachePath, Logger $logger)
    {
        $this->cachePath = $cachePath;
        $this->logger = $logger;
        
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
    }

    public function get(string $key, $default = null)
    {
        // Check if expired
        if (isset($this->ttl[$key]) && $this->ttl[$key] < time()) {
            $this->forget($key);
            return $default;
        }

        return $this->cache[$key] ?? $default;
    }

    public function set(string $key, $value, int $ttl = 3600): void
    {
        $this->cache[$key] = $value;
        $this->ttl[$key] = time() + $ttl;
        $this->logger->debug("Cache set: {$key}", 'CACHE', ['ttl' => $ttl]);
    }

    public function has(string $key): bool
    {
        if (isset($this->ttl[$key]) && $this->ttl[$key] < time()) {
            $this->forget($key);
            return false;
        }

        return isset($this->cache[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->cache[$key], $this->ttl[$key]);
        $this->logger->debug("Cache cleared: {$key}", 'CACHE');
    }

    public function flush(): void
    {
        $this->cache = [];
        $this->ttl = [];
        $this->logger->info("Cache flushed", 'CACHE');
    }

    public function remember(string $key, callable $callback, int $ttl = 3600)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function getStats(): array
    {
        return [
            'items' => count($this->cache),
            'memory' => memory_get_usage(true),
            'hits' => 0, // TODO: Track hits
            'misses' => 0 // TODO: Track misses
        ];
    }
}