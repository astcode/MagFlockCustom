<?php

declare(strict_types=1);

namespace Tests\Components;

use Components\MagDB\MagDB;
use MoBo\Kernel;
use PDOException;
use PHPUnit\Framework\TestCase;

final class MagDBTest extends TestCase
{
    private array $databaseConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseConfig = require __DIR__ . '/../../config/database.php';

        Kernel::resetInstance();
        ob_start();
        try {
            Kernel::getInstance()->initialize(__DIR__ . '/../Fixtures/config/test_config.php');
        } finally {
            ob_end_clean();
        }
    }

    protected function tearDown(): void
    {
        Kernel::resetInstance();
        parent::tearDown();
    }

    public function testStartAndHealthAgainstConfiguredDatabase(): void
    {
        $connections = $this->databaseConfig['connections'] ?? [];
        if (empty($connections)) {
            $this->markTestSkipped('No database connections configured for MagDB tests.');
        }

        $component = new MagDB();
        $component->configure($this->databaseConfig);
        $component->boot();

        try {
            $component->start();
        } catch (PDOException $exception) {
            $this->markTestSkipped('MagDS database not reachable: ' . $exception->getMessage());
        }

        $health = $component->health();
        self::assertSame('healthy', $health['status'], 'MagDB health check did not return healthy status.');

        $default = $this->databaseConfig['default'] ?? 'magdsdb';
        self::assertTrue($component->hasConnection($default));

        $telemetry = Kernel::getInstance()->getTelemetry();
        $openedSamples = $telemetry->snapshot()['metrics']['magdb.connections.opened_total']['values'] ?? [];
        self::assertTrue($this->metricSamplesContain($openedSamples, ['name' => $default]));

        $component->stop();
        $statuses = $component->getConnectionStatuses();
        self::assertSame('unknown', $statuses[$default]['status'] ?? null);

        $closedSamples = $telemetry->snapshot()['metrics']['magdb.connections.closed_total']['values'] ?? [];
        self::assertTrue($this->metricSamplesContain($closedSamples, ['name' => $default]));
    }

    /**
     * @param array<string, array{labels: array<string, string>, value: float}> $samples
     * @param array<string, string> $expectedLabels
     */
    private function metricSamplesContain(array $samples, array $expectedLabels): bool
    {
        foreach ($samples as $sample) {
            if (!isset($sample['labels'])) {
                continue;
            }

            $labels = $sample['labels'];
            $match = true;

            foreach ($expectedLabels as $key => $value) {
                if (($labels[$key] ?? null) !== $value) {
                    $match = false;
                    break;
                }
            }

            if ($match && (($sample['value'] ?? 0.0) > 0.0)) {
                return true;
            }
        }

        return false;
    }
}
