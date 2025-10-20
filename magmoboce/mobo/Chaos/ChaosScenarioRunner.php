<?php

declare(strict_types=1);

namespace MoBo\Chaos;

use MoBo\Kernel;
use MoBo\Logger;
use MoBo\Telemetry;

final class ChaosScenarioRunner
{
    /**
     * @var array<string, ChaosScenarioInterface>
     */
    private array $scenarios = [];

    public function __construct(
        private readonly Logger $logger,
        private readonly ?Telemetry $telemetry
    ) {
    }

    /**
     * @param iterable<ChaosScenarioInterface> $scenarios
     */
    public function registerScenarios(iterable $scenarios): void
    {
        foreach ($scenarios as $scenario) {
            $this->scenarios[$scenario->getName()] = $scenario;
        }
    }

    /**
     * @return array<string, ChaosScenarioInterface>
     */
    public function getScenarios(): array
    {
        return $this->scenarios;
    }

    /**
     * @param list<string>|null $scenarioNames
     * @return list<ChaosScenarioResult>
     */
    public function run(?array $scenarioNames = null): array
    {
        $results = [];
        $targetNames = $scenarioNames === null || $scenarioNames === []
            ? array_keys($this->scenarios)
            : $scenarioNames;

        foreach ($targetNames as $name) {
            if (!isset($this->scenarios[$name])) {
                $results[] = new ChaosScenarioResult(
                    $name,
                    ChaosScenarioResult::STATUS_SKIPPED,
                    0.0,
                    'Scenario not registered',
                    []
                );
                continue;
            }

            $scenario = $this->scenarios[$name];
            $startedAt = microtime(true);
            try {
                $result = $scenario->run();
            } catch (\Throwable $exception) {
                $duration = (microtime(true) - $startedAt) * 1000;
                $result = new ChaosScenarioResult(
                    $name,
                    ChaosScenarioResult::STATUS_FAILED,
                    $duration,
                    $exception->getMessage(),
                    ['trace' => $exception->getTraceAsString()]
                );
            }

            $results[] = $result;
            $this->telemetry?->incrementCounter(
                'chaos.scenarios_total',
                1,
                ['scenario' => $result->getName(), 'result' => $result->getStatus()]
            );
            $this->telemetry?->observeHistogram(
                'chaos.scenario_duration_ms',
                $result->getDurationMs(),
                ['scenario' => $result->getName()]
            );

            $this->logger->info(
                sprintf('Chaos scenario %s: %s', $result->getName(), $result->getStatus()),
                'CHAOS',
                [
                    'duration_ms' => $result->getDurationMs(),
                    'message' => $result->getMessage(),
                    'details' => $result->getDetails(),
                ]
            );
        }

        return $results;
    }

    /**
     * @param list<ChaosScenarioResult> $results
     */
    public function writeReport(array $results, string $reportPath): void
    {
        $directory = dirname($reportPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create chaos report directory: %s', $directory));
        }

        $payload = [
            'generated_at' => gmdate('c'),
            'kernel_instance' => Kernel::getInstance()->getInstanceId(),
            'results' => array_map(static fn (ChaosScenarioResult $result): array => $result->toArray(), $results),
        ];

        file_put_contents($reportPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
