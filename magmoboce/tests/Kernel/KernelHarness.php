<?php

declare(strict_types=1);

namespace Tests\Kernel;

use MoBo\Kernel;
use MoBo\Contracts\ComponentInterface;

/**
 * Utility for spinning up a kernel instance with test components.
 * Used by smoke CLI and upcoming PHPUnit suites.
 */
final class KernelHarness
{
    private const CONFIG_PATH = __DIR__ . '/../Fixtures/config/test_config.php';
    private const RUNTIME_DIR = __DIR__ . '/../runtime';

    /**
     * Boot the kernel with provided component stubs.
     *
     * @param ComponentInterface[] $components
     *
     * @throws \RuntimeException when boot fails
     */
    public static function bootWith(
        array $components,
        bool $includeConfigured = false,
        array $configuredNames = [],
        ?string $configPath = null,
        ?string $logLevel = 'error'
    ): Kernel
    {
        Kernel::resetInstance();
        self::prepareRuntime();

        $kernel = Kernel::getInstance();
        $kernel->initialize($configPath ?? self::CONFIG_PATH);

        if ($logLevel !== null) {
            $kernel->getLogger()->setLevel($logLevel);
        }

        foreach ($components as $component) {
            $kernel->register($component);
        }

        if ($includeConfigured) {
            self::registerConfiguredComponents($kernel, $configuredNames);
        }

        if (!$kernel->boot()) {
            throw new \RuntimeException('Kernel boot failed during harness execution.');
        }

        return $kernel;
    }

    /**
     * Shutdown helper that also resets runtime artefacts for repeat runs.
     */
    public static function shutdown(Kernel $kernel): void
    {
        $kernel->shutdown(5);
        Kernel::resetInstance(false);
    }

    /**
     * Register enabled components from config.
     *
     * @param string[] $onlyNames Restrict registration to the provided component names (case-sensitive)
     */
    private static function registerConfiguredComponents(Kernel $kernel, array $onlyNames = []): void
    {
        $componentConfigPath = __DIR__ . '/../Fixtures/config/test_components.php';
        if (file_exists($componentConfigPath)) {
            $components = require $componentConfigPath;
        } else {
            $components = $kernel->getConfig()->get('components', []);
        }

        foreach ($components as $name => $componentConfig) {
            $enabled = $componentConfig['enabled'] ?? true;
            if (!$enabled) {
                continue;
            }

            if ($onlyNames && !in_array($name, $onlyNames, true)) {
                continue;
            }

            $class = $componentConfig['class'] ?? null;
            if (!$class || !class_exists($class)) {
                continue;
            }

            $instance = new $class();
            $kernel->register($instance);
        }
    }

    private static function prepareRuntime(): void
    {
        $directories = [
            self::RUNTIME_DIR,
            self::RUNTIME_DIR . '/logs',
            self::RUNTIME_DIR . '/cache',
            self::RUNTIME_DIR . '/state',
            self::RUNTIME_DIR . '/telemetry',
            __DIR__ . '/../../storage/logs',
            __DIR__ . '/../../storage/backups',
            __DIR__ . '/../../storage/cache',
            __DIR__ . '/../../storage/state',
            __DIR__ . '/../../storage/telemetry',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException("Unable to create runtime directory: {$dir}");
            }
        }

        // Clean previous run artefacts.
        $logFiles = [
            self::RUNTIME_DIR . '/logs/kernel.log',
            __DIR__ . '/../../storage/logs/mobo.log',
        ];

        foreach ($logFiles as $logFile) {
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        }

        $pathsToClear = [
            __DIR__ . '/../../storage/state/system.json',
            __DIR__ . '/../../storage/telemetry/metrics.prom',
            self::RUNTIME_DIR . '/telemetry/metrics.prom',
        ];

        foreach ($pathsToClear as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

}
