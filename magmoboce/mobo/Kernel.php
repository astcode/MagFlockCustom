<?php

declare(strict_types=1);

namespace MoBo;

use MoBo\Config\ConfigRedactor;
use MoBo\Config\ConfigSchemaValidator;
use MoBo\Config\LayeredConfigLoader;
use MoBo\Contracts\ComponentInterface;
use MoBo\Observability\MetricsServer;
use MoBo\Security\AuditWriter;
use MoBo\Security\CapabilityDeniedException;
use MoBo\Security\CapabilityGate;

class Kernel
{
    private static ?Kernel $instance = null;

    private string $name = 'MoBoCE';
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
    private Telemetry $telemetry;
    private string $instanceId = '';
    private EventSchemaRegistry $eventSchemas;
    private ?LayeredConfigLoader $configLoader = null;
    private AuditWriter $auditWriter;
    private CapabilityGate $capabilityGate;
    private ?MetricsServer $metricsServer = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __clone(): void
    {
    }

    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    public function initialize(string $configPath): void
    {
        $initialLevel = getenv('LOG_LEVEL') ?: 'debug';
        $this->logger = new Logger('storage/logs/mobo.log', $initialLevel);
        $this->logger->info('Initializing MoBoCE Kernel', 'KERNEL');

        $this->config = new ConfigManager($this->logger);

        $configRoot = is_dir($configPath) ? rtrim($configPath, DIRECTORY_SEPARATOR) : dirname($configPath);
        $resolvedRoot = realpath($configRoot);
        if (is_string($resolvedRoot)) {
            $configRoot = rtrim($resolvedRoot, DIRECTORY_SEPARATOR);
        }
        $isLayered = is_dir($configRoot . DIRECTORY_SEPARATOR . 'base');

        if ($isLayered) {
            $environment = getenv('MOBO_ENV') ?: 'development';
            $this->configLoader = new LayeredConfigLoader($configRoot, $environment);
            $schemaPath = $configRoot . '/schema.php';
            if (file_exists($schemaPath)) {
                /** @var array<string, mixed> $schema */
                $schema = require $schemaPath;
                $this->config->setValidator(new ConfigSchemaValidator($schema));
            }

            $redactionPath = $configRoot . '/redaction.php';
            if (file_exists($redactionPath)) {
                /** @var array<int, string> $patterns */
                $patterns = require $redactionPath;
                $this->config->setRedactor(new ConfigRedactor($patterns));
            }

            $config = $this->configLoader->load();
            $this->config->replace($config, $this->configLoader->sources());
            if (!$this->config->validate()) {
                throw new \RuntimeException('Initial configuration failed schema validation.');
            }
        } else {
            $this->config->load($configPath);
            if (!$this->config->validate()) {
                throw new \RuntimeException('Initial configuration failed validation.');
            }
        }

        $this->logger->setRedactor(fn (array $payload): array => $this->config->redact($payload));

        $this->auditWriter = new AuditWriter($this->config, $this->logger);

        $this->logger->setLevel($this->config->get('logging.level', 'info'));

        $this->instanceId = bin2hex(random_bytes(6));
        $this->applyLoggerContext();

        $this->telemetry = new Telemetry();
        $this->metricsServer = new MetricsServer();
        $eventsConfigPath = $configRoot . '/events.php';
        $schemas = file_exists($eventsConfigPath) ? require $eventsConfigPath : [];
        $this->eventSchemas = new EventSchemaRegistry($schemas);

        $this->eventBus = new EventBus($this->logger, $this->telemetry, $this->eventSchemas);
        $this->state = new StateManager('storage/state/system.json', $this->logger);
        $this->cache = new CacheManager('storage/cache', $this->logger);
        $this->registry = new Registry($this->logger, $this->eventBus, $this->telemetry);
        $this->health = new HealthMonitor($this->registry, $this->logger, $this->eventBus, $this->config, $this->telemetry);
        $this->capabilityGate = new CapabilityGate($this->config, $this->logger, $this->auditWriter, $this->eventBus);
        $this->lifecycle = new LifecycleManager(
            $this->registry,
            $this->logger,
            $this->eventBus,
            $this->config,
            $this->telemetry,
            $this->capabilityGate,
            $this->auditWriter
        );
        $this->boot = new BootManager($this->config, $this->logger, $this->eventBus, $this->registry, $this->lifecycle, $this->state, $this->telemetry);

        $this->logger->info('Kernel initialized', 'KERNEL');
    }

    public function boot(): bool
    {
        if ($this->booted) {
            $this->logger->warning('Kernel already booted', 'KERNEL');
            return true;
        }

        $bootStartedAt = microtime(true);
        $success = $this->boot->boot();

        if ($success) {
            $this->booted = true;
            $durationMs = (microtime(true) - $bootStartedAt) * 1000;
            $this->telemetry->setBootDuration($durationMs);
            $this->startMetricsServer();
        }

        return $success;
    }

    public function reloadConfig(): bool
    {
        if ($this->configLoader === null) {
            $this->logger->warning('Config reload requested, but layered loader is not configured.', 'CONFIG');
            return false;
        }

        $actor = (string) $this->config->get('security.default_actor', 'system');
        $environment = $this->configLoader->environment();
        $capabilityContext = [
            'environment' => $environment,
        ];

        try {
            $this->capabilityGate->assertAllowed('kernel.config.reload', $actor, $capabilityContext);
        } catch (CapabilityDeniedException $exception) {
            $this->logger->error('Configuration reload denied by capability gate', 'SECURITY', [
                'capability' => $exception->getCapability(),
                'actor' => $exception->getActor(),
                'context' => $exception->getContext(),
            ]);

            return false;
        }

        try {
            $previous = $this->config->all();
            $next = $this->configLoader->load();
            $sources = $this->configLoader->sources();

            $this->config->replace($next, $sources);
            if (!$this->config->validate()) {
                $this->config->rollback();
                $this->eventBus->emit('config.reload_failed', ['error' => 'Schema validation failed']);
                $this->logger->error('Configuration reload failed validation', 'CONFIG');
                $this->telemetry->incrementCounter('config.reload_attempts_total', 1, ['result' => 'failure']);

                $this->auditWriter->write('kernel.config.reload.failure', [
                    'error' => 'Schema validation failed',
                    'environment' => $environment,
                ]);

                return false;
            }

            $this->logger->setLevel($this->config->get('logging.level', 'info'));
            $this->applyLoggerContext();

            $changed = $this->configLoader->diffKeys($previous, $next);
            $this->eventBus->emit('config.reloaded', [
                'version' => $this->config->get('kernel.version', 'unknown'),
                'changed_keys' => $changed,
            ]);

            $this->logger->info('Configuration reloaded', 'CONFIG', [
                'changed_keys' => $changed,
                'sources' => $sources,
            ]);
            $this->telemetry->incrementCounter('config.reload_attempts_total', 1, ['result' => 'success']);

            $this->auditWriter->write('kernel.config.reload.success', [
                'changed_keys' => $changed,
                'sources' => $sources,
                'environment' => $environment,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->config->rollback();
            $this->eventBus->emit('config.reload_failed', ['error' => $e->getMessage()]);
            $this->logger->error('Configuration reload failed', 'CONFIG', ['error' => $e->getMessage()]);
            $this->telemetry->incrementCounter('config.reload_attempts_total', 1, ['result' => 'failure']);

            $this->auditWriter->write('kernel.config.reload.failure', [
                'error' => $e->getMessage(),
                'environment' => $environment,
            ]);

            return false;
        }
    }

    private function applyLoggerContext(): void
    {
        if (!isset($this->logger) || !isset($this->config)) {
            return;
        }

        $this->logger->setContext([
            'instance_id' => $this->instanceId,
            'environment' => (string) $this->config->get('kernel.environment', 'development'),
            'version' => (string) $this->config->get('kernel.version', '1.0.0'),
        ]);
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
        $this->logger->info('Kernel shutdown initiated', 'KERNEL');
        $this->lifecycle->shutdown($timeout);
        $this->state->setSystemState('stopped');
        $this->metricsServer?->stop();
        $this->logger->info('Kernel shutdown complete', 'KERNEL');
    }

    public function reset(bool $shutdown = true): void
    {
        self::resetInstance($shutdown);
    }

    public static function resetInstance(bool $shutdown = true): void
    {
        if (self::$instance === null) {
            return;
        }

        $instance = self::$instance;

        if ($shutdown && $instance->booted) {
            $instance->shutdown();
        }

        self::$instance = null;
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getConfig(): ConfigManager
    {
        return $this->config;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getConfigLoader(): ?LayeredConfigLoader
    {
        return $this->configLoader;
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

    public function getAuditWriter(): AuditWriter
    {
        return $this->auditWriter;
    }

    public function getCapabilityGate(): CapabilityGate
    {
        return $this->capabilityGate;
    }

    public function getTelemetry(): Telemetry
    {
        return $this->telemetry;
    }

    public function getEventSchemas(): EventSchemaRegistry
    {
        return $this->eventSchemas;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function getVersion(): string
    {
        return (string) $this->config->get('kernel.version', $this->version);
    }

    public function getName(): string
    {
        return (string) $this->config->get('kernel.name', $this->name);
    }

    private function startMetricsServer(): void
    {
        if ($this->metricsServer === null) {
            return;
        }

        try {
            $this->metricsServer->start($this->config, $this->telemetry, $this->logger);
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to start metrics server', 'OBSERVABILITY', [
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}

