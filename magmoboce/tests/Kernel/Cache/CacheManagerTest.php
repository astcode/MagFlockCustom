<?php

declare(strict_types=1);

namespace Tests\Kernel\Cache;

use MoBo\CacheManager;
use MoBo\Logger;
use PHPUnit\Framework\TestCase;

final class CacheManagerTest extends TestCase
{
    private string $runtimePath;
    private CacheManager $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $projectRoot = dirname(__DIR__, 3);
        $this->runtimePath = $projectRoot . '/tests/runtime/cache';
        $this->prepareDirectories([$this->runtimePath, $this->runtimePath . '/logs', $this->runtimePath . '/cache']);

        $logger = new Logger($this->runtimePath . '/logs/cache.log', 'info');
        $this->cache = new CacheManager($this->runtimePath . '/cache', $logger);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testSetGetAndExpiration(): void
    {
        $this->cache->set('key', 'value', 1);
        self::assertSame('value', $this->cache->get('key'));

        sleep(2);

        self::assertNull($this->cache->get('key'));
        self::assertFalse($this->cache->has('key'));
    }

    public function testRememberCachesResultUntilFlush(): void
    {
        $value = $this->cache->remember('calc', fn () => 5, 60);
        self::assertSame(5, $value);
        self::assertSame(5, $this->cache->get('calc'));

        $this->cache->flush();
        self::assertFalse($this->cache->has('calc'));
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
