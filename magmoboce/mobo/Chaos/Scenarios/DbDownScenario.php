<?php

declare(strict_types=1);

namespace MoBo\Chaos\Scenarios;

use Components\MagDB\MagDB;
use MoBo\Chaos\ChaosScenarioInterface;
use MoBo\Chaos\ChaosScenarioResult;
use Components\MagDB\Failover\MagDBFailoverManager;

final class DbDownScenario implements ChaosScenarioInterface
{
    public function __construct(
        private readonly MagDB $magdb
    ) {
    }

    public function getName(): string
    {
        return 'db.down';
    }

    public function getDescription(): string
    {
        return 'Simulates a primary database outage and validates automatic failover.';
    }

    public function run(): ChaosScenarioResult
    {
        $startedAt = microtime(true);
        $manager = $this->magdb->getFailoverManager();

        if (!$manager instanceof MagDBFailoverManager) {
            return new ChaosScenarioResult(
                $this->getName(),
                ChaosScenarioResult::STATUS_SKIPPED,
                0.0,
                'Failover manager unavailable; skipping scenario.',
                []
            );
        }

        $promoted = $manager->heartbeat(autoPromote: true);
        $durationMs = (microtime(true) - $startedAt) * 1000;

        if ($promoted) {
            return new ChaosScenarioResult(
                $this->getName(),
                ChaosScenarioResult::STATUS_PASSED,
                $durationMs,
                'Failover heartbeat completed successfully.',
                [
                    'active_connection' => $this->magdb->getActiveConnectionName(),
                    'last_failover' => $manager->getLastFailover(),
                ]
            );
        }

        return new ChaosScenarioResult(
            $this->getName(),
            ChaosScenarioResult::STATUS_FAILED,
            $durationMs,
            'No suitable replica available for promotion.',
            []
        );
    }
}
