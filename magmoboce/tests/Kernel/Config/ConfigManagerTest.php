<?php

declare(strict_types=1);

namespace Tests\Kernel\Config;

use MoBo\Config\ConfigRedactor;
use MoBo\Config\ConfigSchemaValidator;
use MoBo\ConfigManager;
use MoBo\Logger;
use PHPUnit\Framework\TestCase;

final class ConfigManagerTest extends TestCase
{
    private string $runtimePath;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $projectRoot = dirname(__DIR__, 3);
        $this->runtimePath = $projectRoot . '/tests/runtime/config';
        $this->prepareDirectories([$this->runtimePath, $this->runtimePath . '/logs']);

        $this->logger = new Logger($this->runtimePath . '/logs/config.log', 'info');
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testLoadMergesConfigurationAndSupportsGetSet(): void
    {
        $manager = new ConfigManager($this->logger);
        $manager->load(__DIR__ . '/../../Fixtures/config/test_config.php');

        self::assertSame('MagMoBoCE-Test', $manager->get('kernel.name'));

        $manager->set('custom.value', 42);
        self::assertSame(42, $manager->get('custom.value'));
    }

    public function testValidateFailsWhenRequiredKeysMissing(): void
    {
        $manager = new ConfigManager($this->logger);
        $manager->set('kernel.name', 'Invalid');

        self::assertFalse($manager->validate());
    }

    public function testValidateSucceedsWhenAllKeysPresent(): void
    {
        $manager = new ConfigManager($this->logger);
        $manager->load(__DIR__ . '/../../Fixtures/config/test_config.php');
        $manager->set('logging.path', $this->runtimePath . '/logs/config.log');

        self::assertTrue($manager->validate());
    }

    public function testRedactMasksSensitiveValues(): void
    {
        $manager = new ConfigManager($this->logger);
        $manager->setRedactor(new ConfigRedactor([
            'database.connections.*.password',
            'database.connections.*.username',
        ]));

        $context = [
            'database' => [
                'connections' => [
                    'primary' => [
                        'password' => 'super-secret',
                        'username' => 'admin',
                    ],
                ],
            ],
        ];

        $redacted = $manager->redact($context);

        self::assertSame('[REDACTED]', $redacted['database']['connections']['primary']['password']);
        self::assertStringStartsWith('[HASH:', $redacted['database']['connections']['primary']['username']);
    }

    public function testValidatorReportsSchemaViolations(): void
    {
        $manager = new ConfigManager($this->logger);
        $schema = [
            'kernel' => [
                'type' => 'object',
                'required' => true,
                'children' => [
                    'name' => ['type' => 'string', 'required' => true],
                ],
            ],
        ];

        $manager->setValidator(new ConfigSchemaValidator($schema));
        $manager->replace(['kernel' => []]);

        self::assertFalse($manager->validate());
    }

    private function prepareDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException("Unable to create directory: {$directory}");
            }
        }
    }

    private function cleanup(): void
    {
        if (!is_dir($this->runtimePath)) {
            return;
        }

        $objects = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->runtimePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($objects as $object) {
            $path = $object->getPathname();
            if ($object->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
