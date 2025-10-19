<?php

declare(strict_types=1);

namespace Tests\Kernel\Security;

use MoBo\Config\ConfigRedactor;
use MoBo\ConfigManager;
use MoBo\Logger;
use MoBo\Security\AuditWriter;
use PHPUnit\Framework\TestCase;

final class AuditWriterTest extends TestCase
{
    private string $runtimeDir;
    private string $auditLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runtimeDir = sys_get_temp_dir() . '/audit-writer-' . bin2hex(random_bytes(4));
        $this->auditLog = $this->runtimeDir . '/audit.log';
        $this->prepareDirectories([$this->runtimeDir]);
    }

    protected function tearDown(): void
    {
        $this->cleanDirectory($this->runtimeDir);
        parent::tearDown();
    }

    public function testAuditEntryRedactsSensitivePayload(): void
    {
        $logger = new Logger($this->runtimeDir . '/test.log', 'error');
        $config = new ConfigManager($logger);
        $config->load(__DIR__ . '/../../Fixtures/config/test_config.php');
        $config->set('security.audit_log.path', $this->auditLog);
        $config->setRedactor(new ConfigRedactor(require dirname(__DIR__, 3) . '/config/redaction.php'));

        $writer = new AuditWriter($config, $logger);
        $writer->write('kernel.component.start', [
            'component' => 'MagDB',
            'database' => [
                'connections' => [
                    'primary' => [
                        'password' => 'super-secret',
                        'username' => 'admin',
                    ],
                ],
            ],
        ]);

        self::assertFileExists($this->auditLog);
        $contents = file_get_contents($this->auditLog);
        self::assertNotFalse($contents);

        $entry = json_decode((string) $contents, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('[REDACTED]', $entry['payload']['database']['connections']['primary']['password']);
        self::assertStringStartsWith('[HASH:', $entry['payload']['database']['connections']['primary']['username']);
    }

    /**
     * @param string[] $directories
     */
    private function prepareDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException("Unable to create directory: {$directory}");
            }
        }
    }

    private function cleanDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanDirectory($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
