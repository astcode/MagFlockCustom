<?php

declare(strict_types=1);

namespace MoBo;

final class Telemetry
{
    private const NO_LABELS = '__no_labels__';

    /**
     * @var array<string, array{
     *     type: 'counter'|'gauge',
     *     help: string,
     *     labels: string[],
     *     values: array<string, array{labels: array<string, string>, value: float}>
     * }>
     */
    private array $metrics = [];

    /**
     * @var array<string, array{
     *     type: 'histogram',
     *     help: string,
     *     labels: string[],
     *     buckets: float[],
     *     values: array<string, array{
     *         labels: array<string, string>,
     *         buckets: array<float, float>,
     *         sum: float,
     *         count: float
     *     }>
     * }>
     */
    private array $histograms = [];

    /**
     * Legacy counters/timers kept for backwards compatibility with existing debug tooling.
     *
     * @var array<string, float>
     */
    private array $legacyCounters = [];

    /**
     * @var array<string, array{count:int,total:float,max:float,min:float|null}>
     */
    private array $legacyTimers = [];

    private ?float $uptimeStart = null;
    private ?string $exportPath = null;
    private bool $dirty = false;
    private ?float $lastExport = null;
    private float $exportIntervalSeconds = 1.0;

    public function __construct()
    {
        $this->defineGauge('kernel.boot.time_ms', 'Kernel boot duration in milliseconds.');
        $this->defineCounter('kernel.uptime.seconds', 'Kernel uptime in seconds (monotonic).');
        $this->defineCounter('eventbus.events_total', 'Total events emitted by the kernel event bus.', ['event']);
        $this->defineHistogram(
            'eventbus.handler_duration_ms',
            'Event handler execution duration in milliseconds.',
            ['event'],
            $this->defaultBuckets()
        );
        $this->defineCounter('component.restarts_total', 'Component restarts attempted by the lifecycle manager.', ['component']);
        $this->defineCounter(
            'component.state_changes_total',
            'Component state transitions recorded in the registry.',
            ['component', 'state']
        );
        $this->defineCounter('magdb.connections.opened_total', 'MagDB connections opened.', ['name']);
        $this->defineCounter('magdb.connections.closed_total', 'MagDB connections closed.', ['name']);
        $this->defineHistogram(
            'magdb.health_latency_ms',
            'MagDB health check latency in milliseconds.',
            ['name'],
            $this->databaseBuckets()
        );
        $this->defineCounter('magdb.health_failures_total', 'MagDB health check failures.', ['name']);
        $this->defineGauge('magdb.replica_health', 'MagDB replica health (1 healthy, 0 unhealthy).', ['name']);
        $this->defineGauge('magdb.replica_lag_seconds', 'MagDB replication lag in seconds as observed during heartbeat.', ['name']);
        $this->defineGauge('magdb.replica_latency_ms', 'MagDB heartbeat latency in milliseconds.', ['name']);
        $this->defineHistogram(
            'magdb.replica_latency_ms_histogram',
            'Distribution of MagDB heartbeat latency in milliseconds.',
            ['name'],
            $this->databaseBuckets()
        );
        $this->defineCounter('magdb.failovers_total', 'MagDB failover promotions grouped by reason.', ['reason']);
        $this->defineCounter('magdb.backups_total', 'MagDB backups executed.', []);
        $this->defineGauge('magdb.last_backup_epoch', 'Unix timestamp of the last successful MagDB backup.', []);
        $this->defineCounter('magdb.restores_total', 'MagDB restore operations executed.', []);
        $this->defineGauge('magdb.last_restore_epoch', 'Unix timestamp of the last MagDB restore.', []);
        $this->defineCounter('chaos.scenarios_total', 'Chaos scenario executions grouped by result.', ['scenario', 'result']);
        $this->defineHistogram(
            'chaos.scenario_duration_ms',
            'Chaos scenario execution durations in milliseconds.',
            ['scenario'],
            $this->defaultBuckets()
        );
        $this->defineCounter(
            'config.reload_attempts_total',
            'Configuration reload attempts partitioned by result.',
            ['result']
        );
    }

    public function setExportInterval(float $seconds): void
    {
        $this->exportIntervalSeconds = max(0.1, $seconds);
    }

    public function enableExport(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create telemetry directory: %s', $directory));
        }

        $this->exportPath = $path;
        $this->forceFlush();
    }

    public function forceFlush(): void
    {
        $this->flush(true);
    }

    public function markReady(): void
    {
        $this->uptimeStart = microtime(true);
        $this->touch();
    }

    public function setBootDuration(float $milliseconds): void
    {
        $this->setGauge('kernel.boot.time_ms', $milliseconds);
    }

    public function incrementCounter(string $name, float $value = 1.0, array $labels = []): void
    {
        if (!isset($this->metrics[$name])) {
            $this->increment($name, (int) $value);
            return;
        }

        $metric = &$this->metrics[$name];
        $key = $this->labelKey($metric['labels'], $labels);
        if (!isset($metric['values'][$key])) {
            $metric['values'][$key] = [
                'labels' => $this->normaliseLabels($metric['labels'], $labels),
                'value' => 0.0,
            ];
        }

        $metric['values'][$key]['value'] += $value;
        $this->touch();
    }

    public function setCounterAbsolute(string $name, float $value, array $labels = []): void
    {
        if (!isset($this->metrics[$name])) {
            $this->increment($name, (int) $value);
            return;
        }

        $metric = &$this->metrics[$name];
        $key = $this->labelKey($metric['labels'], $labels);
        $labelsNormalised = $this->normaliseLabels($metric['labels'], $labels);
        $previous = $metric['values'][$key]['value'] ?? 0.0;
        $metric['values'][$key] = [
            'labels' => $labelsNormalised,
            'value' => max($previous, $value),
        ];
        $this->touch();
    }

    public function setGauge(string $name, float $value, array $labels = []): void
    {
        if (!isset($this->metrics[$name])) {
            $this->defineGauge($name, '');
        }

        $metric = &$this->metrics[$name];
        $key = $this->labelKey($metric['labels'], $labels);
        $metric['values'][$key] = [
            'labels' => $this->normaliseLabels($metric['labels'], $labels),
            'value' => $value,
        ];
        $this->touch();
    }

    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        if (!isset($this->histograms[$name])) {
            return;
        }

        $histogram = &$this->histograms[$name];
        $key = $this->labelKey($histogram['labels'], $labels);

        if (!isset($histogram['values'][$key])) {
            $bucketInitial = [];
            foreach ($histogram['buckets'] as $bucket) {
                $bucketInitial[$this->bucketKey($bucket)] = 0.0;
            }

            $histogram['values'][$key] = [
                'labels' => $this->normaliseLabels($histogram['labels'], $labels),
                'buckets' => $bucketInitial,
                'sum' => 0.0,
                'count' => 0.0,
            ];
        }

        $sample = &$histogram['values'][$key];

        foreach ($histogram['buckets'] as $bucket) {
            if ($value <= $bucket) {
                $sample['buckets'][$this->bucketKey($bucket)] += 1.0;
            }
        }

        $sample['sum'] += $value;
        $sample['count'] += 1.0;
        $this->touch();
    }

    public function increment(string $name, int $value = 1): void
    {
        $this->legacyCounters[$name] = ($this->legacyCounters[$name] ?? 0) + $value;
        $this->touch();
    }

    public function recordTiming(string $name, float $milliseconds): void
    {
        if (!isset($this->legacyTimers[$name])) {
            $this->legacyTimers[$name] = [
                'count' => 0,
                'total' => 0.0,
                'max' => 0.0,
                'min' => null,
            ];
        }

        $bucket = &$this->legacyTimers[$name];
        $bucket['count']++;
        $bucket['total'] += $milliseconds;
        $bucket['max'] = max($bucket['max'], $milliseconds);
        $bucket['min'] = $bucket['min'] === null ? $milliseconds : min($bucket['min'], $milliseconds);
        $this->touch();
    }

    public function snapshot(): array
    {
        $this->refreshDynamicMetrics();

        return [
            'metrics' => $this->metrics,
            'histograms' => $this->histograms,
            'legacy' => [
                'counters' => $this->legacyCounters,
                'timers' => $this->legacyTimers,
            ],
        ];
    }

    public function toPrometheus(): string
    {
        $this->refreshDynamicMetrics();

        $lines = [];

        foreach ($this->metrics as $name => $meta) {
            $promName = $this->prometheusName($name);
            $lines[] = sprintf('# HELP %s %s', $promName, $meta['help'] ?: $name);
            $lines[] = sprintf('# TYPE %s %s', $promName, $meta['type']);

            if ($meta['values'] === []) {
                $lines[] = sprintf('%s 0', $promName);
                continue;
            }

            foreach ($meta['values'] as $sample) {
                $labels = $this->formatLabels($sample['labels']);
                $value = $this->formatFloat($sample['value']);
                $lines[] = $labels === ''
                    ? sprintf('%s %s', $promName, $value)
                    : sprintf('%s%s %s', $promName, $labels, $value);
            }
        }

        foreach ($this->histograms as $name => $meta) {
            $promName = $this->prometheusName($name);
            $lines[] = sprintf('# HELP %s %s', $promName, $meta['help'] ?: $name);
            $lines[] = sprintf('# TYPE %s histogram', $promName);

            foreach ($meta['values'] as $sample) {
                $labelsBase = $sample['labels'];

                foreach ($meta['buckets'] as $bucket) {
                    $bucketKey = $this->bucketKey($bucket);
                    $value = $sample['buckets'][$bucketKey] ?? 0.0;
                    $bucketLabel = $bucket === INF ? '+Inf' : $this->formatFloat($bucket);
                    $labels = $this->formatLabels($labelsBase + ['le' => $bucketLabel]);
                    $lines[] = sprintf('%s_bucket%s %s', $promName, $labels, $this->formatFloat($value));
                }

                $labels = $this->formatLabels($labelsBase);
                $lines[] = sprintf('%s_count%s %s', $promName, $labels, $this->formatFloat($sample['count']));
                $lines[] = sprintf('%s_sum%s %s', $promName, $labels, $this->formatFloat($sample['sum']));
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function defineCounter(string $name, string $help, array $labels = []): void
    {
        $this->metrics[$name] = [
            'type' => 'counter',
            'help' => $help,
            'labels' => $labels,
            'values' => [],
        ];
    }

    private function defineGauge(string $name, string $help, array $labels = []): void
    {
        $this->metrics[$name] = [
            'type' => 'gauge',
            'help' => $help,
            'labels' => $labels,
            'values' => [],
        ];
    }

    private function defineHistogram(string $name, string $help, array $labels = [], array $buckets = []): void
    {
        $buckets = $buckets === [] ? $this->defaultBuckets() : $buckets;
        if (end($buckets) !== INF) {
            $buckets[] = INF;
        }

        $this->histograms[$name] = [
            'type' => 'histogram',
            'help' => $help,
            'labels' => $labels,
            'buckets' => $buckets,
            'values' => [],
        ];
    }

    private function refreshDynamicMetrics(): void
    {
        if ($this->uptimeStart !== null && isset($this->metrics['kernel.uptime.seconds'])) {
            $metric = &$this->metrics['kernel.uptime.seconds'];
            $key = $this->labelKey($metric['labels'], []);
            $previous = $metric['values'][$key]['value'] ?? 0.0;
            $metric['values'][$key] = [
                'labels' => $this->normaliseLabels($metric['labels'], []),
                'value' => max($previous, microtime(true) - $this->uptimeStart),
            ];
        }
    }

    private function flush(bool $force = false): void
    {
        if ($this->exportPath === null) {
            $this->dirty = false;
            return;
        }

        if (!$force && !$this->dirty) {
            return;
        }

        $now = microtime(true);
        if (!$force && $this->lastExport !== null && ($now - $this->lastExport) < $this->exportIntervalSeconds) {
            return;
        }

        $payload = $this->toPrometheus();
        file_put_contents($this->exportPath, $payload, LOCK_EX);

        $this->dirty = false;
        $this->lastExport = $now;
    }

    private function touch(): void
    {
        $this->dirty = true;
        $this->flush();
    }

    private function labelKey(array $expected, array $provided): string
    {
        if ($expected === []) {
            return self::NO_LABELS;
        }

        $labels = $this->normaliseLabels($expected, $provided);
        ksort($labels);

        return http_build_query($labels, '', '&');
    }

    /**
     * @param string[] $expected
     * @param array<string, string> $provided
     * @return array<string, string>
     */
    private function normaliseLabels(array $expected, array $provided): array
    {
        $normalised = [];

        foreach ($expected as $label) {
            $normalised[$label] = isset($provided[$label]) ? (string) $provided[$label] : '';
        }

        return $normalised;
    }

    private function prometheusName(string $name): string
    {
        return str_replace('.', '_', $name);
    }

    /**
     * @param array<string, string> $labels
     */
    private function formatLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        $encoded = [];

        foreach ($labels as $key => $value) {
            $encoded[] = sprintf('%s="%s"', $key, $this->escapeLabelValue($value));
        }

        return '{' . implode(',', $encoded) . '}';
    }

    private function escapeLabelValue(string $value): string
    {
        return addcslashes($value, "\\\"\n\r\t");
    }

    private function formatFloat(float $value): string
    {
        if (abs($value) >= 1 || $value === 0.0) {
            return (string) round($value, 6);
        }

        return sprintf('%.6e', $value);
    }

    private function bucketKey(float $bucket): string
    {
        if ($bucket === INF) {
            return '+inf';
        }

        if ($bucket === -INF) {
            return '-inf';
        }

        return rtrim(rtrim(sprintf('%.12F', $bucket), '0'), '.');
    }

    /**
     * @return float[]
     */
    private function defaultBuckets(): array
    {
        return [1.0, 5.0, 10.0, 25.0, 50.0, 100.0, 250.0, 500.0, 1000.0];
    }

    /**
     * @return float[]
     */
    private function databaseBuckets(): array
    {
        return [1.0, 5.0, 10.0, 25.0, 50.0, 100.0, 250.0, 500.0, 1000.0, 2500.0, 5000.0];
    }
}
