<?php

namespace Components\MagDB;

use PDO;
use PDOException;

class Connection
{
    private ?PDO $pdo = null;
    private array $config;
    private string $name;
    private int $queryCount = 0;
    private float $totalQueryTime = 0;
    private ?float $lastQueryTime = null;
    
    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->config = $config;
    }
    
    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }
        
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s',
            $this->config['driver'],
            $this->config['host'],
            $this->config['port'],
            $this->config['database']
        );
        
        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options'] ?? []
            );
        } catch (PDOException $e) {
            throw new \RuntimeException(
                "Failed to connect to database '{$this->name}': " . $e->getMessage()
            );
        }
    }
    
    public function disconnect(): void
    {
        $this->pdo = null;
    }
    
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }
    
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }
    
    public function query(string $sql, array $params = []): array
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->getPdo()->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll();
            
            $this->recordQuery($startTime);
            
            return $result;
        } catch (PDOException $e) {
            throw new \RuntimeException(
                "Query failed on '{$this->name}': " . $e->getMessage()
            );
        }
    }
    
    public function execute(string $sql, array $params = []): int
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->getPdo()->prepare($sql);
            $stmt->execute($params);
            $rowCount = $stmt->rowCount();
            
            $this->recordQuery($startTime);
            
            return $rowCount;
        } catch (PDOException $e) {
            throw new \RuntimeException(
                "Execute failed on '{$this->name}': " . $e->getMessage()
            );
        }
    }
    
    public function fetchOne(string $sql, array $params = [])
    {
        $result = $this->query($sql, $params);
        return $result[0] ?? null;
    }
    
    public function fetchColumn(string $sql, array $params = [], int $column = 0)
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->getPdo()->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchColumn($column);
            
            $this->recordQuery($startTime);
            
            return $result;
        } catch (PDOException $e) {
            throw new \RuntimeException(
                "Fetch column failed on '{$this->name}': " . $e->getMessage()
            );
        }
    }
    
    public function transaction(callable $callback)
    {
        $pdo = $this->getPdo();
        
        try {
            $pdo->beginTransaction();
            $result = $callback($this);
            $pdo->commit();
            return $result;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
    public function ping(): bool
    {
        try {
            $this->getPdo()->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getStats(): array
    {
        return [
            'name' => $this->name,
            'connected' => $this->isConnected(),
            'query_count' => $this->queryCount,
            'total_query_time' => round($this->totalQueryTime, 4),
            'avg_query_time' => $this->queryCount > 0 
                ? round($this->totalQueryTime / $this->queryCount, 4) 
                : 0,
            'last_query_time' => $this->lastQueryTime,
        ];
    }
    
    private function recordQuery(float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        $this->queryCount++;
        $this->totalQueryTime += $duration;
        $this->lastQueryTime = round($duration, 4);
    }
}