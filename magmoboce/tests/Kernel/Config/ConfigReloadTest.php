<?php

declare(strict_types=1);

namespace Tests\Kernel\Config;

use MoBo\Kernel;
use PHPUnit\Framework\TestCase;

final class ConfigReloadTest extends TestCase
{
    private string $configDir;
    private string $environment;
    private ?string $originalLogLevel = null;
    private string $auditLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->environment = 'testing';
        $this->originalLogLevel = getenv('LOG_LEVEL') !== false ? getenv('LOG_LEVEL') : null;
        putenv('LOG_LEVEL=error');
        $_ENV['LOG_LEVEL'] = 'error';
        putenv('MOBO_ENV=' . $this->environment);
        $_ENV['MOBO_ENV'] = $this->environment;

        $projectConfig = dirname(__DIR__, 3) . '/config';
        $this->configDir = sys_get_temp_dir() . '/magmoboce-config-' . bin2hex(random_bytes(4));
        $this->copyDirectory($projectConfig, $this->configDir);

        $this->auditLog = $this->configDir . '/audit.log';
        if (file_exists($this->auditLog)) {
            @unlink($this->auditLog);
        }

        $envDir = $this->configDir . '/environments';
        if (!is_dir($envDir)) {
            mkdir($envDir, 0775, true);
        }

        $this->writeEnvironmentOverride(['logging' => ['level' => 'info']]);

        Kernel::resetInstance();
    }

    protected function tearDown(): void
    {
        Kernel::resetInstance(false);

        if (isset($_ENV['MOBO_ENV'])) {
            unset($_ENV['MOBO_ENV']);
        }
        putenv('MOBO_ENV');

        if ($this->originalLogLevel === null) {
            putenv('LOG_LEVEL');
            unset($_ENV['LOG_LEVEL']);
        } else {
            putenv('LOG_LEVEL=' . $this->originalLogLevel);
            $_ENV['LOG_LEVEL'] = $this->originalLogLevel;
        }

        $this->removeDirectory($this->configDir ?? '');

        parent::tearDown();
    }

    public function testReloadEmitsConfigReloadedEvent(): void
    {
        $kernel = Kernel::getInstance();
        $kernel->initialize($this->configDir . '/mobo.php');
        $kernel->getLogger()->setLevel('error');

        $events = [];
        $eventFailed = [];

        ob_start();
        try {
            $kernel->getEventBus()->on('config.reloaded', static function (array $payload) use (&$events): void {
                $events[] = ['event' => 'config.reloaded', 'payload' => $payload];
            });

            $kernel->getEventBus()->on('config.reload_failed', static function (array $payload) use (&$eventFailed): void {
                $eventFailed[] = $payload;
            });
        } finally {
            ob_end_clean();
        }

        $this->writeEnvironmentOverride(['logging' => ['level' => 'warning']]);

        ob_start();
        try {
            $result = $kernel->reloadConfig();
        } finally {
            ob_end_clean();
        }

        self::assertTrue($result);
        self::assertCount(1, $events);
        self::assertSame('warning', $kernel->getConfig()->get('logging.level'));
        self::assertContains('logging.level', $events[0]['payload']['changed_keys']);
        self::assertSame([], $eventFailed);
        $audit = file_get_contents($this->auditLog) ?: '';
        self::assertStringContainsString('kernel.config.reload.success', $audit);
    }

    public function testReloadRollbackOnValidationFailure(): void
    {
        $kernel = Kernel::getInstance();
        $kernel->initialize($this->configDir . '/mobo.php');
        $kernel->getLogger()->setLevel('error');

        $this->writeEnvironmentOverride(['logging' => ['level' => 'debug']]);
        ob_start();
        try {
            $reloadResult = $kernel->reloadConfig();
        } finally {
            ob_end_clean();
        }
        self::assertTrue($reloadResult);

        $eventsFailed = [];
        ob_start();
        try {
            $kernel->getEventBus()->on('config.reload_failed', static function (array $payload) use (&$eventsFailed): void {
                $eventsFailed[] = $payload;
            });
        } finally {
            ob_end_clean();
        }

        // Break schema: kernel.name must be string.
        $this->writeEnvironmentOverride(['kernel' => ['name' => 123]]);

        ob_start();
        try {
            $failed = $kernel->reloadConfig();
        } finally {
            ob_end_clean();
        }

        self::assertFalse($failed);
        self::assertSame('debug', $kernel->getConfig()->get('logging.level'));
        self::assertNotEmpty($eventsFailed);
        $audit = file_get_contents($this->auditLog) ?: '';
        self::assertStringContainsString('kernel.config.reload.failure', $audit);
    }

    /**
     * @param array<string, mixed> $override
     */
    private function writeEnvironmentOverride(array $override): void
    {
        $envPath = $this->configDir . '/environments/' . $this->environment . '.php';
        $base = [
            'security' => [
                'audit_log' => ['path' => $this->auditLog],
                'default_actor' => 'test-suite',
            ],
        ];
        $payload = array_replace_recursive($base, $override);
        $export = var_export($payload, true);
        file_put_contents($envPath, "<?php\n\nreturn {$export};\n");
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
                    throw new \RuntimeException("Unable to create directory: {$targetPath}");
                }
            } else {
                if (!is_dir(dirname($targetPath)) && !mkdir(dirname($targetPath), 0775, true) && !is_dir(dirname($targetPath))) {
                    throw new \RuntimeException("Unable to create directory: " . dirname($targetPath));
                }
                copy($item->getPathname(), $targetPath);
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}

