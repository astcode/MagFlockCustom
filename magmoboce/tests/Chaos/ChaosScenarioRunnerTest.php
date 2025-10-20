<?php

declare(strict_types=1);

namespace Tests\Chaos;

use MoBo\Chaos\ChaosScenarioInterface;
use MoBo\Chaos\ChaosScenarioResult;
use MoBo\Chaos\ChaosScenarioRunner;
use MoBo\Logger;
use MoBo\Telemetry;
use PHPUnit\Framework\TestCase;

final class ChaosScenarioRunnerTest extends TestCase
{
    public function testRunnerAggregatesScenarioResults(): void
    {
        $logger = new Logger(sys_get_temp_dir() . '/chaos.log', 'error');
        $telemetry = new Telemetry();
        $runner = new ChaosScenarioRunner($logger, $telemetry);
        $runner->registerScenarios([
            new StubScenario('scenario.pass', ChaosScenarioResult::STATUS_PASSED),
            new StubScenario('scenario.fail', ChaosScenarioResult::STATUS_FAILED),
            new StubScenario('scenario.skip', ChaosScenarioResult::STATUS_SKIPPED),
        ]);

        $results = $runner->run();
        self::assertCount(3, $results);
        self::assertSame('scenario.pass', $results[0]->getName());
        self::assertSame(ChaosScenarioResult::STATUS_PASSED, $results[0]->getStatus());
        self::assertSame(ChaosScenarioResult::STATUS_FAILED, $results[1]->getStatus());
        self::assertSame(ChaosScenarioResult::STATUS_SKIPPED, $results[2]->getStatus());
    }

    public function testUnknownScenarioProducesSkippedResult(): void
    {
        $logger = new Logger(sys_get_temp_dir() . '/chaos.log', 'error');
        $runner = new ChaosScenarioRunner($logger, null);
        $results = $runner->run(['missing.scenario']);
        self::assertCount(1, $results);
        self::assertSame(ChaosScenarioResult::STATUS_SKIPPED, $results[0]->getStatus());
    }

    public function testReportWriterPersistsJson(): void
    {
        $logger = new Logger(sys_get_temp_dir() . '/chaos.log', 'error');
        $runner = new ChaosScenarioRunner($logger, null);
        $reportPath = sys_get_temp_dir() . '/chaos-report-' . bin2hex(random_bytes(4)) . '.json';

        $results = [
            new ChaosScenarioResult('scenario', ChaosScenarioResult::STATUS_PASSED, 12.5, 'ok'),
        ];

        $runner->writeReport($results, $reportPath);
        self::assertFileExists($reportPath);

        $payload = json_decode((string) file_get_contents($reportPath), true);
        self::assertArrayHasKey('results', $payload);
        self::assertSame('scenario', $payload['results'][0]['name']);

        @unlink($reportPath);
    }
}

final class StubScenario implements ChaosScenarioInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $status
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return 'stub';
    }

    public function run(): ChaosScenarioResult
    {
        return new ChaosScenarioResult($this->name, $this->status, 1.0, 'stub');
    }
}
