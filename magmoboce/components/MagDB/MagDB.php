<?php

declare(strict_types=1);

namespace Components\MagDB;

use Components\MagDB\Backup\MagDBBackupManager;
use Components\MagDB\Failover\MagDBFailoverManager;
use MoBo\Contracts\ComponentInterface;
use MoBo\Kernel;
use MoBo\Logger;
use MoBo\StateManager;
use MoBo\Telemetry;
use RuntimeException;
use PDO;
use PDOException;

class MagDB implements ComponentInterface
{
    private const DEFAULT_CONNECTION = 'magdsdb';

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * @var array<string, PDO>
     */
    private array $connections = [];

    /**
     * @var array<string, array{
     *     status:string,
     *     last_error:?string,
     *     lag:?float,
     *     latency_ms:?float,
     *     fenced:bool,
     *     last_heartbeat_at:?int
     * }>
     */
    private array $connectionStatuses = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $connectionConfigs = [];

    /**
     * @var array<string, mixed>
     */
    private array $magdsConfig = [];

private ?Logger $logger = null;
private ?Telemetry $telemetry = null;
private ?StateManager $stateManager = null;
private ?MagDBFailoverManager $failoverManager = null;
private ?MagDBBackupManager $backupManager = null;
    private string $activeConnectionName = self::DEFAULT_CONNECTION;

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
        $kernel = Kernel::getInstance();
        $this->logger = $kernel->getLogger();
        $this->telemetry = $kernel->getTelemetry();
        $this->magdsConfig = (array) $kernel->getConfig()->get('magds', []);
    }

    public function boot(): void
    {
        // configuration happens in configure()
    }

    public function start(): void
    {
        $kernel = Kernel::getInstance();
        $this->stateManager = $kernel->getState();

        $this->connectionConfigs = $this->getConnectionConfigs();
        $primary = $this->resolvePrimaryConnection();
        $this->activeConnectionName = $primary;

        if (isset($this->connectionConfigs[$primary])) {
            $this->connect($primary, $this->connectionConfigs[$primary], suppressFailure: true);
        } else {
            $this->setStatus($primary, 'failed', 'Primary connection not defined in database config.');
        }

        foreach ($this->connectionConfigs as $name => $_settings) {
            if (!isset($this->connectionStatuses[$name])) {
                $this->setStatus($name, isset($this->connections[$name]) ? 'healthy' : 'unknown');
            }
        }

        $this->failoverManager = new MagDBFailoverManager(
            $this,
            $this->magdsConfig,
            $kernel->getEventBus(),
            $this->logger,
            $this->telemetry,
            $this->stateManager
        );
        $this->failoverManager->initialize();

        $this->backupManager = new MagDBBackupManager(
            $this,
            $this->magdsConfig,
            $this->logger,
            $this->telemetry,
            $this->stateManager,
            $kernel->getEventBus()
        );
        $this->backupManager->initialize();
    }

    public function stop(): void
    {
        foreach (array_keys($this->connections) as $name) {
            $this->disconnect($name);
        }
    }

    public function shutdown(int $timeout = 30): void
    {
        $this->stop();
    }

    public function health(): array
    {
        $statusMap = [];
        foreach (array_keys($this->connectionConfigs) as $name) {
            $healthy = $this->testConnection($name);
            $status = $this->connectionStatuses[$name] ?? [
                'status' => 'unknown',
                'last_error' => null,
                'lag' => null,
                'latency_ms' => null,
                'fenced' => false,
                'last_heartbeat_at' => null,
            ];
            $statusMap[$name] = [
                'status' => $healthy ? 'healthy' : 'failed',
                'last_error' => $status['last_error'],
                'lag' => $status['lag'],
                'latency_ms' => $status['latency_ms'],
                'fenced' => $status['fenced'],
                'last_heartbeat_at' => $status['last_heartbeat_at'],
            ];
        }

        $overall = 'healthy';
        foreach ($statusMap as $status) {
            if ($status['status'] !== 'healthy') {
                $overall = 'failed';
                break;
            }
        }

        return [
            'status' => $overall,
            'connections' => $statusMap,
        ];
    }

    public function recover(): bool
    {
        return $this->failoverManager?->heartbeat(autoPromote: true) ?? true;
    }

    public function connection(?string $name = null): ?PDO
    {
        $target = $name ?? $this->activeConnectionName;

        if (!isset($this->connections[$target]) && isset($this->connectionConfigs[$target])) {
            $this->connect($target, $this->connectionConfigs[$target], suppressFailure: true);
        }

        return $this->connections[$target] ?? null;
    }

    public function hasConnection(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    public function isConfigured(string $name): bool
    {
        return isset($this->connectionConfigs[$name]);
    }

    public function getActiveConnectionName(): string
    {
        return $this->activeConnectionName;
    }

    public function getFailoverManager(): ?MagDBFailoverManager
    {
        return $this->failoverManager;
    }

    public function getBackupManager(): ?MagDBBackupManager
    {
        return $this->backupManager;
    }

    public function getConnectionStatuses(): array
    {
        return $this->connectionStatuses;
    }

    public function getConnectionConfigs(): array
    {
        return $this->config['connections'] ?? [];
    }

    public function getMagdsConfig(): array
    {
        return $this->magdsConfig;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReplicaConfigs(): array
    {
        return $this->failoverManager?->getReplicaDefinitions() ?? ($this->magdsConfig['replicas'] ?? []);
    }

    public function registerReplica(array $definition): void
    {
        if ($this->failoverManager === null) {
            throw new RuntimeException('Failover manager not initialized.');
        }

        $this->failoverManager->registerReplica($definition);
        $this->refreshReplicaTelemetry();
    }

    public function unregisterReplica(string $connection): void
    {
        if ($this->failoverManager === null) {
            throw new RuntimeException('Failover manager not initialized.');
        }

        $this->failoverManager->unregisterReplica($connection);
        $this->refreshReplicaTelemetry();
    }

    private function resolvePrimaryConnection(): string
    {
        $primary = $this->magdsConfig['primary']['connection'] ?? null;
        if (is_string($primary) && $primary !== '') {
            return $primary;
        }

        if (isset($this->config['default'])) {
            return (string) $this->config['default'];
        }

        return self::DEFAULT_CONNECTION;
    }

    private function connect(string $name, array $settings, bool $suppressFailure = false): void
    {
        $dsn = $settings['dsn'] ?? sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $settings['host'] ?? '127.0.0.1',
            $settings['port'] ?? '5432',
            $settings['database'] ?? ''
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if (($settings['persistent'] ?? true) === true) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        try {
            $pdo = new PDO(
                $dsn,
                $settings['username'] ?? null,
                $settings['password'] ?? null,
                $options
            );

            if (!empty($settings['application_name'])) {
                $pdo->exec("SET application_name = '{$settings['application_name']}'");
            }

            $this->connections[$name] = $pdo;
            $this->setStatus($name, 'healthy');
            $this->telemetry?->incrementCounter('magdb.connections.opened_total', 1, ['name' => $name]);
            $this->logger?->info('MagDB connection established', 'MAGDB', ['connection' => $name]);
        } catch (PDOException $exception) {
            $this->setStatus($name, 'failed', $exception->getMessage());
            $this->telemetry?->increment("magdb.connections.failed.{$name}");
            $this->logger?->error('MagDB connection failed', 'MAGDB', [
                'connection' => $name,
                'error' => $exception->getMessage(),
            ]);

            if (!$suppressFailure) {
                throw $exception;
            }
        }
    }

    private function disconnect(string $name): void
    {
        if (isset($this->connections[$name])) {
            $this->connections[$name] = null;
            unset($this->connections[$name]);
            $this->telemetry?->incrementCounter('magdb.connections.closed_total', 1, ['name' => $name]);
            $this->logger?->info('MagDB connection closed', 'MAGDB', ['connection' => $name]);
            $this->setStatus($name, 'unknown');
            $this->updateReplicaLag($name, null);
        }
    }

    public function promote(string $name, bool $force = false): void
    {
        if (!$this->isConfigured($name)) {
            throw new RuntimeException("Connection {$name} is not configured.");
        }

        if (!isset($this->connections[$name])) {
            $this->connect($name, $this->connectionConfigs[$name], suppressFailure: !$force);
        }

        $previous = $this->activeConnectionName;
        $this->activeConnectionName = $name;
        $this->logger?->warning('MagDB primary connection changed', 'MAGDB', [
            'previous' => $previous,
            'current' => $name,
        ]);
    }

    public function updateReplicaLag(string $name, ?float $lag): void
    {
        $existing = $this->connectionStatuses[$name] ?? [
            'status' => 'unknown',
            'last_error' => null,
            'lag' => null,
            'latency_ms' => null,
            'fenced' => false,
            'last_heartbeat_at' => null,
        ];
        $this->connectionStatuses[$name] = [
            'status' => $existing['status'],
            'last_error' => $existing['last_error'],
            'lag' => $lag,
            'latency_ms' => $existing['latency_ms'],
            'fenced' => $existing['fenced'],
            'last_heartbeat_at' => $existing['last_heartbeat_at'],
        ];

        $this->telemetry?->setGauge(
            'magdb.replica_lag_seconds',
            $lag ?? 0.0,
            ['name' => $name]
        );
    }

    public function testConnection(string $name): bool
    {
        if (!isset($this->connectionConfigs[$name])) {
            $this->setStatus($name, 'failed', 'Connection not configured.');
            return false;
        }

        try {
            if (!isset($this->connections[$name])) {
                $this->connect($name, $this->connectionConfigs[$name], suppressFailure: true);
            }

            if (!isset($this->connections[$name])) {
                return false;
            }

            $pdo = $this->connections[$name];
            $startedAt = microtime(true);

            $statement = $pdo->query('SELECT 1');
            if ($statement === false) {
                throw new RuntimeException('Unable to perform health probe against MagDS connection.');
            }

            $statement->fetch();
            $latencyMs = (microtime(true) - $startedAt) * 1000;

            $this->setStatus($name, 'healthy');
            $this->updateReplicaLatency($name, $latencyMs);
            $this->telemetry?->observeHistogram('magdb.replica_latency_ms_histogram', $latencyMs, ['name' => $name]);
            return true;
        } catch (\Throwable $exception) {
            $this->setStatus($name, 'failed', $exception->getMessage());
            $this->telemetry?->incrementCounter('magdb.health_failures_total', 1, ['name' => $name]);
            return false;
        }
    }

    private function setStatus(string $name, string $status, ?string $error = null): void
    {
        $existing = $this->connectionStatuses[$name] ?? [
            'lag' => null,
            'latency_ms' => null,
            'fenced' => false,
            'last_heartbeat_at' => null,
        ];
        $this->connectionStatuses[$name] = [
            'status' => $status,
            'last_error' => $error,
            'lag' => $existing['lag'],
            'latency_ms' => $existing['latency_ms'],
            'fenced' => $existing['fenced'],
            'last_heartbeat_at' => $existing['last_heartbeat_at'],
        ];

        $this->telemetry?->setGauge(
            'magdb.replica_health',
            $status === 'healthy' ? 1.0 : 0.0,
            ['name' => $name]
        );
    }

    public function updateReplicaLatency(string $name, ?float $latencyMs): void
    {
        $existing = $this->connectionStatuses[$name] ?? [
            'status' => 'unknown',
            'last_error' => null,
            'lag' => null,
            'latency_ms' => null,
            'fenced' => false,
            'last_heartbeat_at' => null,
        ];

        $this->connectionStatuses[$name] = [
            'status' => $existing['status'],
            'last_error' => $existing['last_error'],
            'lag' => $existing['lag'],
            'latency_ms' => $latencyMs,
            'fenced' => $existing['fenced'],
            'last_heartbeat_at' => $existing['last_heartbeat_at'],
        ];

        $this->telemetry?->setGauge(
            'magdb.replica_latency_ms',
            $latencyMs ?? 0.0,
            ['name' => $name]
        );
    }

    private function refreshReplicaTelemetry(): void
    {
        if ($this->failoverManager !== null) {
            $this->failoverManager->heartbeat(autoPromote: false);
        }
    }

    public function markFenced(string $name, bool $fenced): void
    {
        $existing = $this->connectionStatuses[$name] ?? [
            'status' => 'unknown',
            'last_error' => null,
            'lag' => null,
            'latency_ms' => null,
            'fenced' => false,
            'last_heartbeat_at' => null,
        ];

        $this->connectionStatuses[$name] = [
            'status' => $existing['status'],
            'last_error' => $existing['last_error'],
            'lag' => $existing['lag'],
            'latency_ms' => $existing['latency_ms'],
            'fenced' => $fenced,
            'last_heartbeat_at' => $existing['last_heartbeat_at'],
        ];
    }

    public function updateHeartbeatTimestamp(string $name, ?int $timestamp): void
    {
        $existing = $this->connectionStatuses[$name] ?? [
            'status' => 'unknown',
            'last_error' => null,
            'lag' => null,
            'latency_ms' => null,
            'fenced' => false,
            'last_heartbeat_at' => null,
        ];

        $this->connectionStatuses[$name] = [
            'status' => $existing['status'],
            'last_error' => $existing['last_error'],
            'lag' => $existing['lag'],
            'latency_ms' => $existing['latency_ms'],
            'fenced' => $existing['fenced'],
            'last_heartbeat_at' => $timestamp,
        ];
    }
}




