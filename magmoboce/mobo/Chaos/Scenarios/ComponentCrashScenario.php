<?php

declare(strict_types=1);

namespace MoBo\Chaos\Scenarios;

use MoBo\Chaos\ChaosScenarioInterface;
use MoBo\Chaos\ChaosScenarioResult;
use MoBo\LifecycleManager;

final class ComponentCrashScenario implements ChaosScenarioInterface
{
    public function __construct(
        private readonly LifecycleManager $lifecycle,
        private readonly string $componentName = 'MagDB'
    ) {
    }

    public function getName(): string
    {
        return 'component.crash';
    }

    public function getDescription(): string
    {
        return sprintf('Simulates a crash/restart cycle for the %s component.', $this->componentName);
    }

    public function run(): ChaosScenarioResult
    {
        $startedAt = microtime(true);

        $stopped = $this->lifecycle->stop($this->componentName);
        $started = false;
        if ($stopped) {
            $started = $this->lifecycle->start($this->componentName);
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        if ($stopped && $started) {
            return new ChaosScenarioResult(
                $this->getName(),
                ChaosScenarioResult::STATUS_PASSED,
                $durationMs,
                'Component stop/start cycle completed successfully.',
                []
            );
        }

        return new ChaosScenarioResult(
            $this->getName(),
            ChaosScenarioResult::STATUS_FAILED,
            $durationMs,
            'Component failed to recover after crash simulation.',
            [
                'stopped' => $stopped,
                'started' => $started,
            ]
        );
    }
}
