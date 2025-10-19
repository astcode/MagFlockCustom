<?php

declare(strict_types=1);

namespace Tests\Telemetry;

use MoBo\Telemetry;
use PHPUnit\Framework\TestCase;

final class TelemetryTest extends TestCase
{
    public function testPrometheusSnapshotIncludesRegisteredMetrics(): void
    {
        $telemetry = new Telemetry();
        $telemetry->setBootDuration(42.5);
        $telemetry->markReady();
        $telemetry->incrementCounter('eventbus.events_total', 2, ['event' => 'test.event']);
        $telemetry->observeHistogram('eventbus.handler_duration_ms', 15.0, ['event' => 'test.event']);

        usleep(1000);
        $payload = $telemetry->toPrometheus();

        self::assertStringContainsString('kernel_boot_time_ms', $payload);
        self::assertStringContainsString('eventbus_events_total{event="test.event"} 2', $payload);
        self::assertStringContainsString('eventbus_handler_duration_ms_bucket{event="test.event",le="+Inf"} 1', $payload);
    }
}
