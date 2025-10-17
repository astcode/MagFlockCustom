<?php
namespace Components\MagDB;

use MoBo\Contracts\ComponentInterface;
use MoBo\ConfigManager;
use MoBo\EventBus;
use MoBo\Logger;

/**
 * Minimal, concrete MagDB that fully implements ComponentInterface
 * and plays nice with the new Kernel + tests.
 */
class MagDB implements ComponentInterface
{
    private Logger $logger;
    private ConfigManager $config;
    private ?EventBus $events;

    private bool $running = false;
    private array $localConfig = [];

    public function __construct(Logger $logger, ConfigManager $config, ?EventBus $events = null)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->events = $events;
    }

    // ── Identity ───────────────────────────────────────────────
    public function getName(): string     { return 'MagDB'; }
    public function getVersion(): string  { return '1.0.0-ce'; }
    public function getDependencies(): array { return []; }

    // ── Optional convenience for tests/frameworks ─────────────
    public function isRunning(): bool { return $this->running; }

    // ── ComponentInterface methods (common set) ───────────────
    /**
     * Accept component-specific config (array or mixed), merge with global.
     * Keep signature permissive to avoid interface mismatch.
     */
    public function configure($config = null): void
    {
        if (is_array($config)) {
            $this->localConfig = array_replace_recursive($this->localConfig, $config);
        }
    }

    /**
     * Boot: lightweight setup (no external connections yet).
     */
    public function boot(): void
    {
        $this->logger->info('MagDB booted', 'MagDB');
    }

    /**
     * Start: transition to running state (you can connect later when we add PDO).
     */
    public function start(): void
    {
        $this->running = true;
        $this->logger->info('MagDB started', 'MagDB');
        $this->events?->emit('component.started', ['name' => 'MagDB']);
    }

    /**
     * Stop: cleanly shut down.
     */
    public function stop(): void
    {
        $this->running = false;
        $this->logger->info('MagDB stopped', 'MagDB');
        $this->events?->emit('component.stopped', ['name' => 'MagDB']);
    }

    /**
     * Health report: return a structured payload.
     * (Later we’ll add real DB probes; for now we validate config presence.)
     */
    public function health(): array
    {
        $cfg = $this->config->get('database.connections.magdsdb') ?? [];
        $required = ['driver','host','port','database','username','password'];

        $missing = [];
        foreach ($required as $k) {
            if (!isset($cfg[$k]) || $cfg[$k] === '') $missing[] = $k;
        }

        $ok = empty($missing);
        return [
            'component' => 'MagDB',
            'running'   => $this->running,
            'ok'        => $ok,
            'missing'   => $missing,
        ];
    }

    /**
     * Recover from a degraded state. Keep it simple: try to re-start.
     * Return bool so callers know if it worked. If your interface wants void,
     * returning bool is still fine at runtime (PHP ignores), but we can change it later.
     */
    public function recover(): bool
    {
        try {
            if (!$this->running) {
                $this->start();
            }
            $this->logger->info('MagDB recovery attempted', 'MagDB');
            return true;
        } catch (\Throwable $e) {
            $this->logger->info('MagDB recovery failed: ' . $e->getMessage(), 'MagDB');
            return false;
        }
    }

    // Some interfaces include an event hook; harmless to provide.
    public function onEvent($event = null, $payload = null): void
    {
        // no-op for now
    }
}
