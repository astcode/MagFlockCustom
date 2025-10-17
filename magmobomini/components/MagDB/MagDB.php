<?php

namespace Components\MagDB;

use MoBo\Contracts\ComponentInterface;
use MoBo\Logger;
use MoBo\EventBus;
use PDO;

class MagDB implements ComponentInterface
{
    private array $connections = [];
    private array $config = [];
    private string $defaultConnection = 'magui';
    private ?Logger $logger = null;
    private ?EventBus $eventBus = null;
    private array $queryStats = [];
    private bool $isBooted = false;
    
    public function getName(): string
    {
        return 'MagDB';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function getDependencies(): array
    {
        return [];
    }
    
    public function configure(array $config): void
    {
        $this->config = $config;
        $this->defaultConnection = $config['default'] ?? 'magui';
        
        if ($this->logger) {
            $this->logger->info("MagDB configured", 'MAGDB', [
                'default' => $this->defaultConnection,
                'connections' => array_keys($config['connections'] ?? [])
            ]);
        }
    }
    
    public function boot(): void
    {
        // Config should already be loaded via configure() from bootstrap
        if (empty($this->config)) {
            throw new \RuntimeException("MagDB not configured. Ensure database config is loaded in bootstrap.");
        }
        
        $this->isBooted = true;
        
        if ($this->logger) {
            $this->logger->info("MagDB booted", 'MAGDB', [
                'default' => $this->defaultConnection,
                'connections' => array_keys($this->config['connections'] ?? [])
            ]);
        }
    }
    
    public function start(): void
    {
        if (empty($this->config['connections'])) {
            throw new \RuntimeException("No database connections configured");
        }
        
        // Don't auto-connect, just verify config is ready
        if ($this->logger) {
            $this->logger->info("MagDB started", 'MAGDB', [
                'connections' => array_keys($this->config['connections'])
            ]);
        }
    }
    
    public function stop(): void
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }
        
        if ($this->logger) {
            $this->logger->info("MagDB stopped", 'MAGDB');
        }
    }
    
    public function health(): array
    {
        $connectionHealth = [];
        
        foreach ($this->connections as $name => $connection) {
            $connectionHealth[$name] = [
                'connected' => $connection->isConnected(),
                'ping' => $connection->ping(),
                'stats' => $connection->getStats(),
            ];
        }
        
        $allHealthy = !empty($connectionHealth) && 
                      array_reduce($connectionHealth, fn($carry, $item) => $carry && $item['ping'], true);
        
        return [
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'connections' => $connectionHealth,
            'default' => $this->defaultConnection,
        ];
    }
    
    public function recover(): bool
    {
        try {
            foreach ($this->connections as $connection) {
                if (!$connection->ping()) {
                    $connection->disconnect();
                    $connection->connect();
                }
            }
            return true;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Recovery failed", 'MAGDB', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
    
    public function shutdown(int $timeout = 30): void
    {
        $this->stop();
    }
    
    // Public API
    
    public function connection(?string $name = null): Connection
    {
        $name = $name ?? $this->defaultConnection;
        
        if (!isset($this->connections[$name])) {
            if (!isset($this->config['connections'][$name])) {
                throw new \InvalidArgumentException("Connection '{$name}' not configured");
            }
            
            $config = array_merge(
                $this->config['connections'][$name],
                ['options' => $this->config['options'] ?? []]
            );
            
            $this->connections[$name] = new Connection($name, $config);
            $this->connections[$name]->connect();
            
            if ($this->logger) {
                $this->logger->debug("Connection created", 'MAGDB', ['connection' => $name]);
            }
        }
        
        return $this->connections[$name];
    }
    
    public function query(string $sql, array $params = [], ?string $connection = null): array
    {
        $conn = $this->connection($connection);
        
        if ($this->logger) {
            $this->logger->debug("Query executed", 'MAGDB', [
                'connection' => $conn->getName(),
                'sql' => $sql,
                'params' => $params
            ]);
        }
        
        return $conn->query($sql, $params);
    }
    
    public function execute(string $sql, array $params = [], ?string $connection = null): int
    {
        $conn = $this->connection($connection);
        
        if ($this->logger) {
            $this->logger->debug("Execute statement", 'MAGDB', [
                'connection' => $conn->getName(),
                'sql' => $sql,
                'params' => $params
            ]);
        }
        
        return $conn->execute($sql, $params);
    }
    
    public function fetchOne(string $sql, array $params = [], ?string $connection = null)
    {
        return $this->connection($connection)->fetchOne($sql, $params);
    }
    
    public function fetchColumn(string $sql, array $params = [], int $column = 0, ?string $connection = null)
    {
        return $this->connection($connection)->fetchColumn($sql, $params, $column);
    }
    
    public function transaction(callable $callback, ?string $connection = null)
    {
        return $this->connection($connection)->transaction($callback);
    }
    
    public function getStats(): array
    {
        $stats = [];
        foreach ($this->connections as $name => $connection) {
            $stats[$name] = $connection->getStats();
        }
        return $stats;
    }
}