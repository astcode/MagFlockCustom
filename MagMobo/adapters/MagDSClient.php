<?php
namespace Adapters;

use PDO;
use PDOException;

class MagDSClient {
    private ?PDO $pdo = null;
    private array $stats = ['queries' => 0, 'total_time' => 0.0, 'avg_time' => 0.0];

    public function connect(array $dsn): void {
        $host = $dsn['host'] ?? '127.0.0.1';
        $port = (string)($dsn['port'] ?? '5432');
        $db   = $dsn['database'] ?? 'postgres';
        $user = $dsn['username'] ?? 'postgres';
        $pass = $dsn['password'] ?? '';

        $uri = "pgsql:host={$host};port={$port};dbname={$db}";
        $this->pdo = new PDO($uri, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public function fetchOne(string $sql, array $params = []): array {
        $t0 = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $this->track(microtime(true) - $t0);
        return $row;
    }

    public function fetchColumn(string $sql, array $params = [], int $col = 0) {
        $t0 = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn($col);
        $this->track(microtime(true) - $t0);
        return $val;
    }

    public function stats(): array {
        $s = $this->stats;
        $s['avg_time'] = $s['queries'] ? $s['total_time'] / $s['queries'] : 0.0;
        return ['magui' => $s]; // test expects a named profile
    }

    private function track(float $dt): void {
        $this->stats['queries']++;
        $this->stats['total_time'] += $dt;
    }
}
