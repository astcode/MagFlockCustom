<?php

namespace MoBo;

use MoBo\Contracts\ComponentInterface;

class Kernel
{
    // ── Singleton + lifecycle flags ─────────────────────────────
    private static ?self $instance = null;

    private bool $initialized = false;
    private bool $booted = false;

    // ── Core managers ───────────────────────────────────────────
    private ?ConfigManager    $config    = null;
    private ?Registry         $registry  = null;
    private ?EventBus         $events    = null;   // standardize on "events"
    private ?HealthMonitor    $health    = null;
    private ?LifecycleManager $lifecycle = null;
    private ?CacheManager     $cache     = null;
    private ?StateManager     $state     = null;
    private ?Logger           $logger    = null;
    private ?BootManager      $boot      = null;

    // Keep initial raw config (merged from bootstrap/config files)
    private array $rawConfig = [];

    /** Keep ctor private; real wiring happens in init() */
    private function __construct(array $config = [])
    {
        $this->rawConfig = $config;
    }

    /** Public factory used by tests and by the platform */
    public static function getInstance(array $config = []): self
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        } elseif (!empty($config)) {
            self::$instance->rawConfig = array_replace_recursive(self::$instance->rawConfig, $config);
        }
        return self::$instance;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Legacy initialize() kept for compatibility; delegates to init()
     * If your old code called initialize($configPath), we still work.
     */
    public function initialize(string $configPath): void
    {
        // Seed rawConfig with a hint for a config directory if your ConfigManager supports it
        $this->rawConfig['__config_path__'] = $configPath;
        $this->init();
    }

    /** One-time initialization; safe to call multiple times */
    public function init(): void
    {
        if ($this->initialized) return;

        // 1) Logger first (so ConfigManager can receive it)
        $defaultLogPath = \dirname(__DIR__) . '/storage/logs/mobo.log';
        @is_dir(\dirname($defaultLogPath)) || @mkdir(\dirname($defaultLogPath), 0755, true);

        if ($this->logger === null) {
            // Prefer Logger::__construct(path[, level]) if present
            $ref = new \ReflectionClass(Logger::class);
            $ctor = $ref->getConstructor();
            $required = $ctor ? $ctor->getNumberOfRequiredParameters() : 0;
            if ($required <= 1) $this->logger = new Logger($defaultLogPath);
            else                $this->logger = new Logger($defaultLogPath, 'debug');
        }

        // 2) CONFIG MANAGER — create it before any method_exists() checks
        if ($this->config === null) {
            // try (Logger, array) then (Logger)
            try {
                $this->config = new ConfigManager($this->logger, $this->rawConfig);
            } catch (\TypeError $e) {
                $this->config = new ConfigManager($this->logger);
            }
        }
        if (!$this->config) {
            throw new \RuntimeException('ConfigManager failed to initialize');
        }

        // 3) Load config from disk (prefer directory loader; else load files individually)
        if (method_exists($this->config, 'loadFiles')) {
            $dir = \dirname(__DIR__) . '/config';
            if (is_dir($dir)) {
                $this->config->loadFiles($dir);
            }
            if (!empty($this->rawConfig['__config_path__']) && is_dir($this->rawConfig['__config_path__'])) {
                $this->config->loadFiles($this->rawConfig['__config_path__']);
            }
        } elseif (method_exists($this->config, 'load')) {
            $primary = \dirname(__DIR__) . '/config/mobo.php';
            if (is_file($primary)) {
                $this->config->load($primary);
            }
            $db = \dirname(__DIR__) . '/config/database.php';
            if (is_file($db)) {
                $this->config->load($db);
            }
        }

        // 4) Align logger with config
        $logCfg   = $this->config->get('logging') ?? [];
        $logPath  = $logCfg['path']  ?? $defaultLogPath;
        $logLevel = $logCfg['level'] ?? 'debug';
        if (method_exists($this->logger, 'setLevel')) $this->logger->setLevel($logLevel);
        if (method_exists($this->logger, 'setPath') && $logPath !== $defaultLogPath) {
            @is_dir(\dirname($logPath)) || @mkdir(\dirname($logPath), 0755, true);
            $this->logger->setPath($logPath);
        }

        // 5) Event bus
        $this->events = $this->events ?? new EventBus($this->logger);

        // 6) Registry — signature is (Logger, EventBus, ConfigManager)
        $this->registry = $this->registry ?? new Registry($this->logger, $this->events, $this->config);

        // after registry is created and before initialized=true
        $this->loadConfiguredComponents();

        // 7) Remaining managers via reflection so we stop guessing
        // paths with safe defaults
        $rootDir      = \dirname(__DIR__);
        $cacheDir     = $this->config->get('cache.path',  $rootDir . '/storage/cache');
        $stateFile    = $this->config->get('state.file',  $rootDir . '/storage/state/system.json');

        // ensure dirs/files exist
        @is_dir($cacheDir) || @mkdir($cacheDir, 0755, true);
        @is_dir(\dirname($stateFile)) || @mkdir(\dirname($stateFile), 0755, true);
        if (!is_file($stateFile)) {
            @file_put_contents($stateFile, json_encode(['system' => 'unknown'], JSON_PRETTY_PRINT));
        }

        // build the managers explicitly (no reflection)
        $this->cache     = $this->cache     ?? new CacheManager($cacheDir,  $this->logger);
        $this->state     = $this->state     ?? new StateManager($stateFile, $this->logger);
        $this->health    = $this->health    ?? new HealthMonitor($this->registry, $this->logger, $this->events, $this->config);
        $this->lifecycle = $this->lifecycle ?? new LifecycleManager($this->registry, $this->logger, $this->events, $this->config);
        $this->boot      = $this->boot      ?? new BootManager($this->config, $this->logger, $this->events, $this->registry, $this->lifecycle, $this->state);

        $this->initialized = true;
    }

    /** Boot once, even if called multiple times */
    public function boot(): void
    {
        $this->init();
        if ($this->booted) return;

        if (method_exists($this->boot, 'run'))       $this->boot->run();
        elseif (method_exists($this->boot, 'start')) $this->boot->start();

        $this->booted = true;
    }

    // ── Registry helpers ───────────────────────────────────────
    public function register(ComponentInterface $component): void
    {
        $this->registry->register($component);
    }
    public function get(string $name): ?ComponentInterface
    {
        return $this->registry->get($name);
    }
    public function has(string $name): bool
    {
        return $this->registry->has($name);
    }

    // ── Shutdown ───────────────────────────────────────────────
    public function shutdown(int $timeout = 30): void
    {
        if ($this->logger)   $this->logger->info("Kernel shutdown initiated", 'KERNEL');
        if ($this->lifecycle) $this->lifecycle->shutdown($timeout);
        if ($this->state)     $this->state->setSystemState('stopped');
        if ($this->logger)   $this->logger->info("Kernel shutdown complete", 'KERNEL');
    }

    // ── Accessors (modern) ─────────────────────────────────────
    public function logger(): Logger                 { return $this->logger; }
    public function config(): ConfigManager          { return $this->config; }
    public function registry(): Registry             { return $this->registry; }
    public function events(): EventBus               { return $this->events; }
    public function health(): HealthMonitor          { return $this->health; }
    public function lifecycle(): LifecycleManager    { return $this->lifecycle; }
    public function cache(): CacheManager            { return $this->cache; }
    public function state(): StateManager            { return $this->state; }
    public function isInitialized(): bool            { return $this->initialized; }
    public function isBooted(): bool                 { return $this->booted; }

    // ── Accessors (legacy compat) ─────────────────────────────
    public function getConfig(): ConfigManager       { return $this->config(); }
    public function getLogger(): Logger              { return $this->logger(); }
    public function getEventBus(): EventBus          { return $this->events(); }
    public function getRegistry(): Registry          { return $this->registry(); }
    public function getHealth(): HealthMonitor       { return $this->health(); }
    public function getLifecycle(): LifecycleManager { return $this->lifecycle(); }
    public function getState(): StateManager         { return $this->state(); }
    public function getCache(): CacheManager         { return $this->cache(); }
    public function getVersion(): string             { return $this->config->get('kernel.version', '1.0.0'); }
    public function getName(): string                { return $this->config->get('kernel.name', 'MoBo'); }

    // ── Small DI helper to stop guessing ctor params ──────────
// ── Small DI helper to stop guessing ctor params ──────────
private function buildWithDeps(string $class)
{
    $ref  = new \ReflectionClass($class);
    $ctor = $ref->getConstructor();
    if (!$ctor || $ctor->getNumberOfParameters() === 0) {
        return new $class();
    }

    $args = [];
    foreach ($ctor->getParameters() as $p) {
        $t = $p->getType();
        $named = ($t instanceof \ReflectionNamedType);
        $typeName = $named ? $t->getName() : null;

        // Class-typed deps
        if ($typeName && !$t->isBuiltin()) {
            switch ($typeName) {
                case Logger::class:          $args[] = $this->logger;   continue 2;
                case ConfigManager::class:   $args[] = $this->config;   continue 2;
                case EventBus::class:        $args[] = $this->events;   continue 2;
                case Registry::class:        $args[] = $this->registry; continue 2;
                case self::class:            $args[] = $this;           continue 2;
            }
        }

        // Primitive / builtin or unknown: resolve per class + param name
        $resolved = $this->resolvePrimitiveParam($class, $p);
        if ($resolved !== null) {
            $args[] = $resolved;
            continue;
        }

        // Fallback: default value or null
        $args[] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
    }

    return $ref->newInstanceArgs($args);
}

/**
 * Resolve well-known primitive parameters from config (with safe defaults).
 * Creates directories/files if needed.
 */
private function resolvePrimitiveParam(string $class, \ReflectionParameter $p)
{
    $name = $p->getName();

    $rootDir      = \dirname(__DIR__);
    $defaultCache = $this->config->get('cache.path',  $rootDir . '/storage/cache');
    $defaultState = $this->config->get('state.file',  $rootDir . '/storage/state/system.json');
    $defaultLogs  = $this->config->get('logging.path', $rootDir . '/storage/logs/mobo.log');

    // ensure base dirs exist
    @is_dir(\dirname($defaultCache)) || @mkdir(\dirname($defaultCache), 0755, true);
    @is_dir(\dirname($defaultState)) || @mkdir(\dirname($defaultState), 0755, true);
    @is_dir(\dirname($defaultLogs))  || @mkdir(\dirname($defaultLogs),  0755, true);

    switch ($class) {
        case CacheManager::class:
            if ($name === 'cachePath' || $name === 'path' || $name === 'dir') {
                @is_dir($defaultCache) || @mkdir($defaultCache, 0755, true);
                return $defaultCache;
            }
            if ($name === 'logPath') return $defaultLogs;
            break;

        case StateManager::class:
            // your ctor: __construct(string $statePath, Logger $logger)
            if ($name === 'statePath' || $name === 'path' || $name === 'file' || $name === 'filename') {
                // touch the file if missing so json_encode has a target
                if (!is_file($defaultState)) {
                    @file_put_contents($defaultState, json_encode(['system' => 'unknown'], JSON_PRETTY_PRINT));
                }
                return $defaultState;
            }
            if ($name === 'logPath') return $defaultLogs;
            break;

        case HealthMonitor::class:
        case LifecycleManager::class:
        case BootManager::class:
            if ($name === 'interval' || $name === 'checkInterval') {
                return (int)($this->config->get('health.check_interval', 30));
            }
            if ($name === 'timeout') return (int)($this->config->get('health.timeout', 5));
            if ($name === 'retries') return (int)($this->config->get('health.retries', 3));
            break;

        case Logger::class:
            if ($name === 'path' || $name === 'logPath') return $defaultLogs;
            if ($name === 'level') return (string)($this->config->get('logging.level', 'debug'));
            break;
    }

    // Generic fallbacks
    if ($name === 'path' || $name === 'dir' || $name === 'directory') return $defaultCache;
    if ($name === 'file' || $name === 'filename') return $defaultState;

    return null;
}




private function loadConfiguredComponents(): void
{
    $defs = $this->config->get('components', []);
    foreach ($defs as $name => $meta) {
        if (!(bool)($meta['enabled'] ?? true)) continue;
        $class = $meta['class'] ?? null;
        if (!$class || !class_exists($class)) {
            $this->logger->info("Component $name skipped (class missing)", 'BOOT');
            continue;
        }
        // Construct with known deps: Logger, ConfigManager, EventBus
        $component = new $class($this->logger, $this->config, $this->events);
        $this->registry->register($component);
    }
}



}
