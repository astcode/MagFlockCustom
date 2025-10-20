<?php

declare(strict_types=1);

namespace Components\MagDB\Failover;

use Components\MagDB\MagDB;
use MoBo\EventBus;
use MoBo\Logger;
use MoBo\StateManager;
use MoBo\Telemetry;
use PDO;
use RuntimeException;

final class MagDBFailoverManager
{
    private const STATE_KEY = 'magds.failover';

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $replicaConfig = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $dynamicReplicas = [];

    /**
     * @var array<string, int>
     */
    private array $quarantine = [];

    /**
     * @var array<string, float>
     */
    private array $replicaScores = [];

    /**
     * @var array<string, array{
     *     healthy:bool,
     *     lag:?float,
     *     latency_ms:?float,
     *     fenced:bool,
     *     timestamp:int
     * }>
     */
    private array $heartbeatCache = [];

    private ?string $lastFailover = null;

    public function __construct(
        private readonly MagDB $magdb,
        private array $config,
        private readonly EventBus $eventBus,
        private readonly ?Logger $logger,
        private readonly ?Telemetry $telemetry,
        private readonly ?StateManager $stateManager
    ) {
    }

    public function initialize(): void
    {
        $this->loadState();

        $base = $this->config['replicas'] ?? [];
        if (!is_array($base)) {
            $base = [];
        }

        $this->replicaConfig = $this->mergeReplicaDefinitions($base, $this->dynamicReplicas);
        $this->clearExpiredQuarantine();
        $this->hydrateHeartbeatState();
        $this->refreshReplicaStatuses();
    }

    public function heartbeat(bool $autoPromote = false): bool
    {
        $this->clearExpiredQuarantine();

        $primaryName = $this->magdb->getActiveConnectionName();
        $primaryHealthy = $this->magdb->testConnection($primaryName);
        $this->refreshReplicaStatuses();

        if ($primaryHealthy && !$this->isQuarantined($primaryName)) {
            return true;
        }

        if (!$autoPromote) {
            return false;
        }

        return $this->handleFailover('auto');
    }

    public function forcePromote(string $connection, bool $force = false): bool
    {
        return $this->handleFailover('manual', $connection, $force);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function status(): array
    {
        $statuses = [];
        $active = $this->magdb->getActiveConnectionName();
        $configuredPrimary = (string) ($this->config['primary']['connection'] ?? $active);
        $healthMap = $this->magdb->getConnectionStatuses();

        $configuredStatus = $healthMap[$configuredPrimary] ?? ['status' => 'unknown', 'last_error' => null, 'lag' => null];
        $statuses[] = [
            'name' => $configuredPrimary,
            'role' => 'primary-configured',
            'healthy' => $configuredStatus['status'] === 'healthy',
            'last_error' => $configuredStatus['last_error'],
            'lag' => $configuredStatus['lag'],
            'latency_ms' => $configuredStatus['latency_ms'] ?? null,
            'active' => $configuredPrimary === $active,
            'auto_promote' => false,
            'priority' => PHP_INT_MAX,
            'quarantined' => $this->isQuarantined($configuredPrimary),
            'fenced' => $configuredStatus['fenced'] ?? false,
            'last_heartbeat_at' => $configuredStatus['last_heartbeat_at'] ?? null,
            'score' => $this->replicaScores[$configuredPrimary] ?? 0.0,
        ];

        if ($configuredPrimary !== $active) {
            $activeStatus = $healthMap[$active] ?? ['status' => 'unknown', 'last_error' => null, 'lag' => null];
            $statuses[] = [
                'name' => $active,
                'role' => 'primary-active',
                'healthy' => $activeStatus['status'] === 'healthy',
                'last_error' => $activeStatus['last_error'],
                'lag' => $activeStatus['lag'],
                'latency_ms' => $activeStatus['latency_ms'] ?? null,
                'active' => true,
                'auto_promote' => false,
                'priority' => PHP_INT_MAX,
                'quarantined' => $this->isQuarantined($active),
                'fenced' => $activeStatus['fenced'] ?? false,
                'last_heartbeat_at' => $activeStatus['last_heartbeat_at'] ?? null,
                'score' => $this->replicaScores[$active] ?? 0.0,
            ];
        }

        foreach ($this->replicaConfig as $replica) {
            $name = (string) ($replica['connection'] ?? '');
            if ($name === '') {
                continue;
            }

            $status = $healthMap[$name] ?? ['status' => 'unknown', 'last_error' => null, 'lag' => null];
            $statuses[] = [
                'name' => $name,
                'role' => 'replica',
                'healthy' => $status['status'] === 'healthy',
                'last_error' => $status['last_error'],
                'lag' => $status['lag'],
                'latency_ms' => $status['latency_ms'] ?? null,
                'active' => $name === $active,
                'auto_promote' => (bool) ($replica['auto_promote'] ?? false),
                'priority' => (int) ($replica['priority'] ?? 0),
                'quarantined' => $this->isQuarantined($name),
                'fenced' => $status['fenced'] ?? false,
                'last_heartbeat_at' => $status['last_heartbeat_at'] ?? null,
                'score' => $this->replicaScores[$name] ?? 0.0,
            ];
        }

        return $statuses;
    }

    public function getLastFailover(): ?string
    {
        return $this->lastFailover;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReplicaDefinitions(): array
    {
        return $this->replicaConfig;
    }

    public function registerReplica(array $definition): void
    {
        $connection = (string) ($definition['connection'] ?? '');
        if ($connection === '') {
            throw new RuntimeException('Replica registration requires --connection=<name>.');
        }

        if (!$this->magdb->isConfigured($connection)) {
            throw new RuntimeException("Connection {$connection} is not defined in database config.");
        }

        $definition['connection'] = $connection;
        if (isset($definition['priority'])) {
            $definition['priority'] = (int) $definition['priority'];
        }
        if (isset($definition['weight'])) {
            $definition['weight'] = (int) $definition['weight'];
        }
        if (isset($definition['lag_threshold_seconds'])) {
            $definition['lag_threshold_seconds'] = (int) $definition['lag_threshold_seconds'];
        }

        $this->dynamicReplicas = array_values(array_filter(
            $this->dynamicReplicas,
            static fn (array $replica): bool => (string) ($replica['connection'] ?? '') !== $connection
        ));
        $this->dynamicReplicas[] = $definition;

        $this->replicaConfig = $this->mergeReplicaDefinitions($this->config['replicas'] ?? [], $this->dynamicReplicas);
        $this->persistState();
        $this->refreshReplicaStatuses();
    }

    public function unregisterReplica(string $connection): void
    {
        $this->dynamicReplicas = array_values(array_filter(
            $this->dynamicReplicas,
            static fn (array $replica): bool => (string) ($replica['connection'] ?? '') !== $connection
        ));
        unset($this->quarantine[$connection]);

        $this->replicaConfig = $this->mergeReplicaDefinitions($this->config['replicas'] ?? [], $this->dynamicReplicas);
        $this->persistState();
        $this->refreshReplicaStatuses();
    }

    private function handleFailover(string $reason, ?string $target = null, bool $force = false): bool
    {
        $previousPrimary = $this->magdb->getActiveConnectionName();
        $this->emitEvent('magdb.failover.detected', [
            'previous' => $previousPrimary,
            'reason' => $reason,
            'target' => $target,
        ]);

        foreach ($this->prioritisedReplicas() as $replica) {
            $name = (string) ($replica['connection'] ?? '');
            if (
                $name === '' ||
                ($target !== null && $name !== $target) ||
                $this->isQuarantined($name)
            ) {
                continue;
            }

            $healthy = $this->magdb->testConnection($name);
            if (!$healthy && !$force) {
                continue;
            }

            try {
                $this->magdb->promote($name, $force || (bool) ($replica['auto_promote'] ?? false));
                $this->lastFailover = gmdate('c');
                $this->performFencing($previousPrimary);
                $this->postFailoverValidation($name);
                $this->telemetry?->incrementCounter('magdb.failovers_total', 1, ['reason' => $reason]);
                $this->emitEvent('magdb.failover.completed', [
                    'new_primary' => $name,
                    'reason' => $reason,
                ]);
                $this->logger?->warning('MagDB failover completed', 'MAGDB', [
                    'new_primary' => $name,
                    'reason' => $reason,
                ]);
                $this->persistState();
                $this->refreshReplicaStatuses();
                return true;
            } catch (\Throwable $exception) {
                $this->logger?->error('MagDB failover promotion failed', 'MAGDB', [
                    'candidate' => $name,
                    'reason' => $reason,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->emitEvent('magdb.failover.failed', [
            'reason' => $reason,
            'target' => $target,
        ]);
        $this->telemetry?->incrementCounter('magdb.failovers_total', 1, ['reason' => 'failed']);
        return false;
    }

    private function refreshReplicaStatuses(): void
    {
        $names = [$this->magdb->getActiveConnectionName()];

        foreach ($this->replicaConfig as $replica) {
            $name = (string) ($replica['connection'] ?? '');
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        $timestamp = time();
        foreach ($names as $name) {
            $healthy = $this->magdb->testConnection($name);
            $lag = $healthy ? $this->measureLag($name) : null;
            $this->magdb->updateReplicaLag($name, $lag);
            $this->magdb->updateHeartbeatTimestamp($name, $timestamp);

            $status = $this->magdb->getConnectionStatuses()[$name] ?? [
                'latency_ms' => null,
                'fenced' => $this->isQuarantined($name),
            ];

            $this->heartbeatCache[$name] = [
                'healthy' => $healthy,
                'lag' => $lag,
                'latency_ms' => $status['latency_ms'] ?? null,
                'fenced' => $status['fenced'] ?? false,
                'timestamp' => $timestamp,
            ];
        }

        $this->persistState();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function prioritisedReplicas(): array
    {
        $healthMap = $this->magdb->getConnectionStatuses();
        $preferredTags = $this->config['failover']['preferred_tags'] ?? [];

        $replicas = $this->replicaConfig;
        usort($replicas, function (array $a, array $b) use ($healthMap, $preferredTags): int {
            $scoreA = $this->calculateScore($a, $healthMap, $preferredTags);
            $scoreB = $this->calculateScore($b, $healthMap, $preferredTags);
            return $scoreB <=> $scoreA;
        });

        return $replicas;
    }

    /**
     * @param array<string, mixed> $replica
     * @param array<string, array{status:string,last_error:?string,lag:?float}> $healthMap
     * @param array<string, string> $preferredTags
     */
    private function calculateScore(array $replica, array $healthMap, array $preferredTags): float
    {
        $name = (string) ($replica['connection'] ?? '');
        $health = $healthMap[$name] ?? [
            'status' => 'unknown',
            'lag' => null,
            'latency_ms' => null,
            'fenced' => false,
        ];
        $score = (float) ($replica['priority'] ?? 0) + (float) ($replica['weight'] ?? 0);

        if ((bool) ($replica['auto_promote'] ?? false)) {
            $score += 50.0;
        }

        if ($health['status'] !== 'healthy') {
            $score -= 1000.0;
        }

        if ($this->isQuarantined($name)) {
            $score -= 1000.0;
        }

        $lagThreshold = (int) ($replica['lag_threshold_seconds'] ?? 0);
        if ($lagThreshold > 0 && isset($health['lag']) && $health['lag'] !== null && $health['lag'] > $lagThreshold) {
            $score -= 500.0;
        }

        if (!empty($preferredTags) && isset($replica['tags']) && is_array($replica['tags'])) {
            $tagBonus = (float) ($this->config['failover']['weights']['preferred_tag_bonus'] ?? 25.0);
            foreach ($preferredTags as $key => $value) {
                if (($replica['tags'][$key] ?? null) === $value) {
                    $score += $tagBonus;
                }
            }
        }

        $weights = $this->config['failover']['weights'] ?? [];
        if (isset($health['lag']) && $health['lag'] !== null) {
            $score -= (float) $health['lag'] * (float) ($weights['lag_seconds'] ?? 25.0);
        }

        if (isset($health['latency_ms']) && $health['latency_ms'] !== null) {
            $score -= (float) $health['latency_ms'] * (float) ($weights['latency_ms'] ?? 0.25);
        }

        if (!empty($weights['fenced_penalty']) && ($health['fenced'] ?? false) === true) {
            $score -= (float) $weights['fenced_penalty'];
        }

        $this->replicaScores[$name] = $score;

        return $score;
    }

    private function measureLag(string $connection): ?float
    {
        $pdo = $this->magdb->connection($connection);
        if (!$pdo instanceof PDO) {
            return null;
        }

        try {
            $statement = $pdo->query(
                "SELECT CASE WHEN pg_is_in_recovery()
                    THEN EXTRACT(EPOCH FROM (now() - pg_last_xact_replay_timestamp()))
                    ELSE 0 END AS lag"
            );
            if ($statement === false) {
                return null;
            }

            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row === false || $row['lag'] === null) {
                return null;
            }

            return (float) $row['lag'];
        } catch (\Throwable $exception) {
            $this->logger?->debug('Unable to measure replica lag', 'MAGDB', [
                'connection' => $connection,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function postFailoverValidation(string $newPrimary): void
    {
        $pdo = $this->magdb->connection($newPrimary);
        if (!$pdo instanceof PDO) {
            return;
        }

        try {
            $pdo->exec('SET statement_timeout = 5000;');

            $stmt = $pdo->query('SELECT pg_is_in_recovery() AS in_recovery');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if ($row && ($row['in_recovery'] ?? 't') === 't') {
                $this->logger?->warning('Promoted node still reports in recovery.', 'MAGDB', [
                    'connection' => $newPrimary,
                ]);
            }

            $probe = $pdo->query('SELECT current_timestamp as ts, current_database() as db');
            if ($probe === false) {
                throw new \RuntimeException('Unable to run timestamp probe on promoted primary.');
            }
            $probe->fetch(PDO::FETCH_ASSOC);

            $schemaCheck = $pdo->query("SELECT count(*) AS tables FROM information_schema.tables WHERE table_schema NOT IN ('pg_catalog','information_schema')");
            if ($schemaCheck === false) {
                throw new \RuntimeException('Unable to enumerate tables during post-failover validation.');
            }
            $schemaCheck->fetch(PDO::FETCH_ASSOC);

            $this->magdb->updateReplicaLag($newPrimary, 0.0);
            $this->magdb->updateHeartbeatTimestamp($newPrimary, time());
            $this->emitEvent('magdb.failover.validation_passed', [
                'new_primary' => $newPrimary,
            ]);
            $this->logger?->info('Post-failover smoke tests passed.', 'MAGDB', [
                'connection' => $newPrimary,
            ]);
        } catch (\Throwable $exception) {
            $this->logger?->warning('Post-failover validation encountered errors.', 'MAGDB', [
                'connection' => $newPrimary,
                'error' => $exception->getMessage(),
            ]);
            $this->emitEvent('magdb.failover.validation_failed', [
                'new_primary' => $newPrimary,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $base
     * @param array<int, array<string, mixed>> $dynamic
     * @return array<int, array<string, mixed>>
     */
    private function mergeReplicaDefinitions(array $base, array $dynamic): array
    {
        $indexed = [];

        foreach ($base as $replica) {
            $name = (string) ($replica['connection'] ?? '');
            if ($name === '') {
                continue;
            }
            $indexed[$name] = $replica;
        }

        foreach ($dynamic as $replica) {
            $name = (string) ($replica['connection'] ?? '');
            if ($name === '') {
                continue;
            }
            $indexed[$name] = array_merge($indexed[$name] ?? [], $replica);
        }

        return array_values($indexed);
    }

    private function loadState(): void
    {
        if ($this->stateManager === null) {
            return;
        }

        $state = $this->stateManager->get(self::STATE_KEY, []);
        $this->dynamicReplicas = array_values($state['replicas'] ?? []);
        $this->quarantine = $state['quarantine'] ?? [];
        $this->lastFailover = $state['last_failover'] ?? null;
        $this->heartbeatCache = $state['heartbeats'] ?? [];
    }

    private function persistState(): void
    {
        if ($this->stateManager === null) {
            return;
        }

        $this->stateManager->set(self::STATE_KEY, [
            'replicas' => $this->dynamicReplicas,
            'quarantine' => $this->quarantine,
            'last_failover' => $this->lastFailover,
            'heartbeats' => $this->heartbeatCache,
        ]);
    }

    private function clearExpiredQuarantine(): void
    {
        if ($this->quarantine === []) {
            return;
        }

        $now = time();
        $updated = false;
        foreach ($this->quarantine as $connection => $timestamp) {
            if ($timestamp <= $now) {
                unset($this->quarantine[$connection]);
                $this->magdb->markFenced($connection, false);
                $updated = true;
            }
        }

        if ($updated) {
            $this->persistState();
        }
    }

    private function isQuarantined(string $connection): bool
    {
        return isset($this->quarantine[$connection]) && $this->quarantine[$connection] > time();
    }

    private function setQuarantine(string $connection, ?int $secondsOverride = null): void
    {
        $seconds = $secondsOverride ?? (int) ($this->config['failover']['quarantine_seconds'] ?? 60);
        if ($seconds <= 0) {
            return;
        }

        $this->quarantine[$connection] = time() + $seconds;
        $this->magdb->markFenced($connection, true);
        $this->persistState();
    }

    private function performFencing(string $previousPrimary): void
    {
        if ($previousPrimary === '') {
            return;
        }

        $fencingConfig = $this->config['fencing'] ?? [];
        $graceSeconds = isset($fencingConfig['grace_period_seconds'])
            ? (int) $fencingConfig['grace_period_seconds']
            : null;
        $this->setQuarantine($previousPrimary, $graceSeconds);

        $sessionTimeout = (int) ($fencingConfig['session_timeout_seconds'] ?? 0);
        if ($sessionTimeout <= 0) {
            return;
        }

        $pdo = $this->magdb->connection($previousPrimary);
        if (!$pdo instanceof PDO) {
            $this->logger?->debug('Unable to contact previous primary for fencing; connection unavailable.', 'MAGDB', [
                'connection' => $previousPrimary,
            ]);
            return;
        }

        try {
            $pdo->exec(sprintf('SET statement_timeout = %d;', max($sessionTimeout * 1000, 1000)));
            $terminated = $pdo->query("
                SELECT pg_terminate_backend(pid)
                FROM pg_stat_activity
                WHERE datname = current_database()
                  AND pid <> pg_backend_pid();
            ");
            $count = 0;
            if ($terminated !== false) {
                while ($terminated->fetch()) {
                    $count++;
                }
            }

            $this->logger?->warning('Fenced previous primary and drained existing sessions.', 'MAGDB', [
                'connection' => $previousPrimary,
                'terminated_sessions' => $count,
                'timeout_seconds' => $sessionTimeout,
            ]);
        } catch (\Throwable $exception) {
            $this->logger?->warning('Fencing reminder: unable to terminate sessions on previous primary.', 'MAGDB', [
                'connection' => $previousPrimary,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function hydrateHeartbeatState(): void
    {
        if ($this->heartbeatCache === []) {
            return;
        }

        foreach ($this->heartbeatCache as $connection => $snapshot) {
            if (isset($snapshot['lag'])) {
                $this->magdb->updateReplicaLag($connection, $snapshot['lag']);
            }
            if (isset($snapshot['latency_ms'])) {
                $this->magdb->updateReplicaLatency($connection, $snapshot['latency_ms']);
            }
            if (isset($snapshot['timestamp'])) {
                $this->magdb->updateHeartbeatTimestamp($connection, (int) $snapshot['timestamp']);
            }
            if (isset($snapshot['fenced'])) {
                $this->magdb->markFenced($connection, (bool) $snapshot['fenced']);
            }
        }
    }

    private function emitEvent(string $name, array $payload): void
    {
        $payload['timestamp'] = microtime(true);
        $this->eventBus->emit($name, $payload);
    }
}
