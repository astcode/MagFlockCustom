<?php

declare(strict_types=1);

namespace MoBo\Chaos\Scenarios;

use Components\MagDB\MagDB;
use MoBo\Chaos\ChaosScenarioInterface;
use MoBo\Chaos\ChaosScenarioResult;
use PDOException;

final class DbLatencyScenario implements ChaosScenarioInterface
{
    public function __construct(
        private readonly MagDB $magdb,
        private readonly float $targetMs = 500.0
    ) {
    }

    public function getName(): string
    {
        return 'db.latency.500ms';
    }

    public function getDescription(): string
    {
        return 'Introduces a 500ms query delay and verifies latency stays within acceptable bounds.';
    }

    public function run(): ChaosScenarioResult
    {
        $connection = $this->magdb->connection();
        if ($connection === null) {
            return new ChaosScenarioResult(
                $this->getName(),
                ChaosScenarioResult::STATUS_SKIPPED,
                0.0,
                'MagDB connection unavailable; skipping latency check.',
                []
            );
        }

        $startedAt = microtime(true);
        try {
            $connection->query('SELECT pg_sleep(0.5)');
        } catch (PDOException $exception) {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            return new ChaosScenarioResult(
                $this->getName(),
                ChaosScenarioResult::STATUS_FAILED,
                $durationMs,
                'Latency probe query failed: ' . $exception->getMessage(),
                []
            );
        } catch (\Throwable $exception) {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            return new ChaosScenarioResult(
                $this->getName(),
                ChaosScenarioResult::STATUS_FAILED,
                $durationMs,
                'Unexpected error during latency probe: ' . $exception->getMessage(),
                []
            );
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;
        $healthy = $this->magdb->testConnection($this->magdb->getActiveConnectionName());

        $upperBound = $this->targetMs + 300.0; // allow tolerance

        if ($healthy && $durationMs >= $this->targetMs && $durationMs <= $upperBound) {
            return new ChaosScenarioResult(
                $this->getName(),
                ChaosScenarioResult::STATUS_PASSED,
                $durationMs,
                'Latency probe completed within expected range.',
                [
                    'measured_ms' => $durationMs,
                    'target_ms' => $this->targetMs,
                    'upper_bound_ms' => $upperBound,
                ]
            );
        }

        return new ChaosScenarioResult(
            $this->getName(),
            ChaosScenarioResult::STATUS_FAILED,
            $durationMs,
            'Latency outside expected range or health check failed.',
            [
                'measured_ms' => $durationMs,
                'target_ms' => $this->targetMs,
                'upper_bound_ms' => $upperBound,
                'magdb_healthy' => $healthy,
            ]
        );
    }
}
