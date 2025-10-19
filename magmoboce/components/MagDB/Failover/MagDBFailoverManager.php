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
            'active' => $configuredPrimary === $active,
            'auto_promote' => false,
            'priority' => PHP_INT_MAX,
            'quarantined' => $this->isQuarantined($configuredPrimary),
        ];

        if ($configuredPrimary !== $active) {
            $activeStatus = $healthMap[$active] ?? ['status' => 'unknown', 'last_error' => null, 'lag' => null];
            $statuses[] = [
                'name' => $active,
                'role' => 'primary-active',
                'healthy' => $activeStatus['status'] === 'healthy',
                'last_error' => $activeStatus['last_error'],
                'lag' => $activeStatus['lag'],
                'active' => true,
                'auto_promote' => false,
                'priority' => PHP_INT_MAX,
                'quarantined' => $this->isQuarantined($active),
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
                'active' => $name === $active,
                'auto_promote' => (bool) ($replica['auto_promote'] ?? false),
                'priority' => (int) ($replica['priority'] ?? 0),
                'quarantined' => $this->isQuarantined($name),
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
                $this->setQuarantine($previousPrimary);
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

        foreach ($names as $name) {
            $healthy = $this->magdb->testConnection($name);
            $lag = $healthy ? $this->measureLag($name) : null;
            $this->magdb->updateReplicaLag($name, $lag);
        }
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
    private function calculateScore(array $replica, array $healthMap, array $preferredTags): int
    {
        $name = (string) ($replica['connection'] ?? '');
        $health = $healthMap[$name] ?? ['status' => 'unknown', 'lag' => null];
        $score = (int) ($replica['priority'] ?? 0) + (int) ($replica['weight'] ?? 0);

        if ((bool) ($replica['auto_promote'] ?? false)) {
            $score += 50;
        }

        if ($health['status'] !== 'healthy') {
            $score -= 1000;
        }

        if ($this->isQuarantined($name)) {
            $score -= 1000;
        }

        $lagThreshold = (int) ($replica['lag_threshold_seconds'] ?? 0);
        if ($lagThreshold > 0 && isset($health['lag']) && $health['lag'] !== null && $health['lag'] > $lagThreshold) {
            $score -= 500;
        }

        if (!empty($preferredTags) && isset($replica['tags']) && is_array($replica['tags'])) {
            foreach ($preferredTags as $key => $value) {
                if (($replica['tags'][$key] ?? null) === $value) {
                    $score += 25;
                }
            }
        }

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
            $stmt = $pdo->query('SELECT pg_is_in_recovery() AS in_recovery');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if ($row && ($row['in_recovery'] ?? 't') === 't') {
                $this->logger?->warning('Promoted node still reports in recovery.', 'MAGDB', [
                    'connection' => $newPrimary,
                ]);
            }

            $probe = $pdo->query('SELECT current_timestamp');
            if ($probe === false) {
                $this->logger?->warning('Post-failover probe query failed.', 'MAGDB', [
                    'connection' => $newPrimary,
                ]);
            }

            $this->magdb->updateReplicaLag($newPrimary, 0.0);
        } catch (\Throwable $exception) {
            $this->logger?->warning('Post-failover validation encountered errors.', 'MAGDB', [
                'connection' => $newPrimary,
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

    private function setQuarantine(string $connection): void
    {
        $seconds = (int) ($this->config['failover']['quarantine_seconds'] ?? 60);
        if ($seconds <= 0) {
            return;
        }

        $this->quarantine[$connection] = time() + $seconds;
        $this->persistState();
    }

    private function emitEvent(string $name, array $payload): void
    {
        $payload['timestamp'] = microtime(true);
        $this->eventBus->emit($name, $payload);
    }
}
