<?php

namespace MoBo;

use MoBo\Contracts\ComponentInterface;

class Kernel
{
    private static ?Kernel $instance = null;
    
    private string $name = 'MoBoMini';
    private string $version = '1.0.0';
    private bool $booted = false;

    private ConfigManager $config;
    private Logger $logger;
    private EventBus $eventBus;
    private Registry $registry;
    private HealthMonitor $health;
    private LifecycleManager $lifecycle;
    private StateManager $state;
    private CacheManager $cache;
    private BootManager $boot;

    private function __construct()
    {
        // Private constructor for singleton
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
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

    public function initialize(string $configPath): void
    {
        // Initialize logger first
        $this->logger = new Logger('storage/logs/mobo.log', 'debug');
        $this->logger->info("Initializing MoBoMini Kernel", 'KERNEL');

        // Initialize config
        $this->config = new ConfigManager($this->logger);
        $this->config->load($configPath);

        // Update logger level from config
        $logLevel = $this->config->get('logging.level', 'info');
        $this->logger->setLevel($logLevel);

        // Initialize core subsystems
        $this->eventBus = new EventBus($this->logger);
        $this->state = new StateManager('storage/state/system.json', $this->logger);
        $this->cache = new CacheManager('storage/cache', $this->logger);
        $this->registry = new Registry($this->logger, $this->eventBus);
        $this->health = new HealthMonitor($this->registry, $this->logger, $this->eventBus, $this->config);
        $this->lifecycle = new LifecycleManager($this->registry, $this->logger, $this->eventBus, $this->config);
        $this->boot = new BootManager($this->config, $this->logger, $this->eventBus, $this->registry, $this->lifecycle, $this->state);

        $this->logger->info("Kernel initialized", 'KERNEL');
    }

    public function boot(): bool
    {
        if ($this->booted) {
            $this->logger->warning("Kernel already booted", 'KERNEL');
            return true;
        }

        $success = $this->boot->boot();

        if ($success) {
            $this->booted = true;
        }

        return $success;
    }

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

    public function shutdown(int $timeout = 30): void
    {
        $this->logger->info("Kernel shutdown initiated", 'KERNEL');
        $this->lifecycle->shutdown($timeout);
        $this->state->setSystemState('stopped');
        $this->logger->info("Kernel shutdown complete", 'KERNEL');
    }

    // Getters for subsystems
    public function getConfig(): ConfigManager
    {
        return $this->config;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getEventBus(): EventBus
    {
        return $this->eventBus;
    }

    public function getRegistry(): Registry
    {
        return $this->registry;
    }

    public function getHealth(): HealthMonitor
    {
        return $this->health;
    }

    public function getLifecycle(): LifecycleManager
    {
        return $this->lifecycle;
    }

    public function getState(): StateManager
    {
        return $this->state;
    }

    public function getCache(): CacheManager
    {
        return $this->cache;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function getVersion(): string
    {
        return $this->config->get('kernel.version', '1.0.0');
    }

    public function getName(): string
    {
        return $this->config->get('kernel.name', 'MoBoMini');
    }
}