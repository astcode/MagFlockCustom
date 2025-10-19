<?php

namespace MoBo;

class BootManager
{
    private ConfigManager $config;
    private Logger $logger;
    private EventBus $eventBus;
    private Registry $registry;
    private LifecycleManager $lifecycle;
    private StateManager $state;
    private ?Telemetry $telemetry;

    public function __construct(
        ConfigManager $config,
        Logger $logger,
        EventBus $eventBus,
        Registry $registry,
        LifecycleManager $lifecycle,
        StateManager $state,
        ?Telemetry $telemetry = null
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->eventBus = $eventBus;
        $this->registry = $registry;
        $this->lifecycle = $lifecycle;
        $this->state = $state;
        $this->telemetry = $telemetry;
    }

    public function boot(): bool
    {
        try {
            $this->logger->info("=== MoBoCE Boot Sequence Started ===", 'BOOT');
            $this->eventBus->emit('system.boot');
            $this->telemetry?->increment('boot.sequence.start');

            // STAGE 1: PRE-BOOT
            $this->logger->info("[STAGE 1] PRE-BOOT", 'BOOT');
            if (!$this->preBoot()) {
                throw new \RuntimeException("Pre-boot failed");
            }
            $this->telemetry?->increment('boot.stage.pre_boot.success');

            // STAGE 2: KERNEL INIT
            $this->logger->info("[STAGE 2] KERNEL INIT", 'BOOT');
            if (!$this->kernelInit()) {
                throw new \RuntimeException("Kernel initialization failed");
            }
            $this->telemetry?->increment('boot.stage.kernel_init.success');

            // STAGE 3: POST (Power-On Self-Test)
            $this->logger->info("[STAGE 3] POST (Power-On Self-Test)", 'BOOT');
            if (!$this->post()) {
                throw new \RuntimeException("POST failed");
            }
            $this->telemetry?->increment('boot.stage.post.success');

            // STAGE 4: COMPONENT LOADING
            $this->logger->info("[STAGE 4] COMPONENT LOADING", 'BOOT');
            if (!$this->loadComponents()) {
                throw new \RuntimeException("Component loading failed");
            }
            $this->telemetry?->increment('boot.stage.component_load.success');

            // STAGE 5: SERVICE START
            $this->logger->info("[STAGE 5] SERVICE START", 'BOOT');
            if (!$this->startServices()) {
                throw new \RuntimeException("Service start failed");
            }
            $this->telemetry?->increment('boot.stage.service_start.success');

            // STAGE 6: READY
            $this->logger->info("[STAGE 6] SYSTEM READY", 'BOOT');
            $this->ready();
            $this->telemetry?->increment('boot.sequence.ready');

            $this->logger->info("=== MoBoCE Boot Complete ===", 'BOOT');

            return true;

        } catch (\Throwable $e) {
            $this->logger->critical("Boot failed: " . $e->getMessage(), 'BOOT', [
                'trace' => $e->getTraceAsString()
            ]);

            $this->eventBus->emit('system.boot_failed', [
                'error' => $e->getMessage()
            ]);
            $this->telemetry?->increment('boot.sequence.failed');

            return false;
        }
    }

    private function preBoot(): bool
    {
        // Validate configuration
        if (!$this->config->validate()) {
            $this->logger->error("Configuration validation failed", 'BOOT');
            return false;
        }

        $this->logger->info("✓ Configuration validated", 'BOOT');

        // Check file permissions
        $paths = ['storage/logs', 'storage/backups', 'storage/cache', 'storage/state'];
        foreach ($paths as $path) {
            if (!is_writable($path)) {
                $this->logger->error("Path not writable: {$path}", 'BOOT');
                return false;
            }
        }

        $this->logger->info("✓ File permissions verified", 'BOOT');

        return true;
    }

    private function kernelInit(): bool
    {
        $this->logger->info("✓ Event bus initialized", 'BOOT');
        $this->logger->info("✓ Registry initialized", 'BOOT');
        $this->logger->info("✓ State manager initialized", 'BOOT');

        return true;
    }

    private function post(): bool
    {
        // Test database connectivity
        $dbConfig = $this->config->get('database.connections.magds');
        
        if ($dbConfig && is_array($dbConfig)) {
            try {
                $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
                $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password']);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->logger->info("✓ Database connection verified", 'BOOT');
            } catch (\PDOException $e) {
                $this->logger->warning("Database connection failed (non-critical)", 'BOOT', ['error' => $e->getMessage()]);
                // Don't fail boot if database is not available
            }
        } else {
            $this->logger->info("✓ Database config not found (skipping test)", 'BOOT');
        }

        // Verify component dependencies
        try {
            $this->registry->resolveDependencies();
            $this->logger->info("✓ Component dependencies resolved", 'BOOT');
        } catch (\Throwable $e) {
            // If no components registered, this is OK
            if (count($this->registry->list()) === 0) {
                $this->logger->info("✓ No components to resolve", 'BOOT');
            } else {
                $this->logger->error("Dependency resolution failed", 'BOOT', ['error' => $e->getMessage()]);
                return false;
            }
        }

        return true;
    }

    private function loadComponents(): bool
    {
        $components = $this->registry->list();

        foreach ($components as $component) {
            $name = $component['name'];
            $instance = $this->registry->get($name);

            try {
                $this->logger->info("Loading component: {$name}", 'BOOT');
                
                // Configure component with appropriate config
                if ($name === 'MagDB') {
                    $componentConfig = $this->config->get('database', []);
                } else {
                    $componentConfig = $this->config->get("components.{$name}", []);
                }
                $instance->configure($componentConfig);

                // Boot component
                $instance->boot();

                $this->registry->setState($name, 'loaded');
                $this->logger->info("✓ Component loaded: {$name}", 'BOOT');

            } catch (\Throwable $e) {
                $this->logger->error("Failed to load component: {$name}", 'BOOT', [
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        }

        return true;
    }

    private function startServices(): bool
    {
        if (!$this->lifecycle->startAll()) {
            $this->logger->error("Failed to start all services", 'BOOT');
            return false;
        }

        $this->logger->info("✓ All services started", 'BOOT');

        return true;
    }

    private function ready(): void
    {
        $bootTime = date('c');
        $this->config->set('system.boot_time', $bootTime);
        $this->state->set('system.boot_time', $bootTime);
        $this->state->setSystemState('running');

        $this->eventBus->emit('system.ready');
        $this->telemetry?->markReady();
        $this->telemetry?->increment('system.ready.total');

        $this->displayBootSummary();
    }

    private function displayBootSummary(): void
    {
        $components = $this->registry->list();
        
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║              MoBoCE System Ready                         ║\n";
        echo "╠════════════════════════════════════════════════════════════╣\n";
        echo "║ Components Loaded: " . str_pad(count($components), 37) . "║\n";
        
        foreach ($components as $component) {
            $status = $component['state'] === 'running' ? '✓' : '✗';
            $line = "║  {$status} " . str_pad($component['name'], 52) . "║\n";
            echo $line;
        }
        
        echo "╠════════════════════════════════════════════════════════════╣\n";
        $systemUrl = $this->config->get('urls.mobo') ?: 'Not configured';
        echo "║ System: " . str_pad($systemUrl, 43) . "║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";
    }
}
