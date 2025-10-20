<?php

declare(strict_types=1);

namespace Tests\MagDS;

use Components\MagDB\MagDB;
use MoBo\Kernel;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Kernel\KernelHarness;

final class MagDBFailoverTest extends TestCase
{
    private ?Kernel $kernel = null;

    protected function tearDown(): void
    {
        if ($this->kernel !== null) {
            KernelHarness::shutdown($this->kernel);
            $this->kernel = null;
        }

        parent::tearDown();
    }

    public function testAutoFailoverPromotesReplica(): void
    {
        ob_start();
        $this->kernel = KernelHarness::bootWith(
            [],
            includeConfigured: true,
            configuredNames: ['MagDB'],
            configPath: __DIR__ . '/../Fixtures/config/test_magds_failover.php',
            logLevel: 'error'
        );
        ob_end_clean();

        $magdb = $this->kernel->get('MagDB');
        self::assertInstanceOf(MagDB::class, $magdb);

        $manager = $magdb->getFailoverManager();
        self::assertNotNull($manager);

        $result = $manager->heartbeat(autoPromote: true);
        self::assertTrue($result, 'Failover manager should promote an available replica');

        self::assertSame('magds_replica', $magdb->getActiveConnectionName());
        $connection = $magdb->connection();
        self::assertInstanceOf(PDO::class, $connection);

        $statement = $connection->query('SELECT 1');
        self::assertNotFalse($statement);

        self::assertNotNull($manager->getLastFailover());
    }

    public function testStatusReportsReplicaHealth(): void
    {
        ob_start();
        $this->kernel = KernelHarness::bootWith(
            [],
            includeConfigured: true,
            configuredNames: ['MagDB'],
            configPath: __DIR__ . '/../Fixtures/config/test_magds_failover.php',
            logLevel: 'error'
        );
        ob_end_clean();

        $magdb = $this->kernel->get('MagDB');
        self::assertInstanceOf(MagDB::class, $magdb);
        $manager = $magdb->getFailoverManager();
        self::assertNotNull($manager);

        $manager->heartbeat(autoPromote: true);
        $status = $manager->status();

        $primary = array_values(array_filter($status, static fn (array $row): bool => $row['role'] === 'primary-configured'))[0] ?? null;
        self::assertNotNull($primary);
        self::assertFalse($primary['healthy'], 'Primary should be marked unhealthy in test fixture.');
        self::assertTrue($primary['quarantined']);
        self::assertTrue($primary['fenced']);
        self::assertNotNull($primary['last_heartbeat_at']);

        $active = array_values(array_filter($status, static fn (array $row): bool => $row['role'] === 'primary-active'))[0] ?? null;
        self::assertNotNull($active);
        self::assertTrue($active['healthy']);
        self::assertTrue($active['active']);
        self::assertArrayHasKey('latency_ms', $active);
        self::assertIsNumeric($active['latency_ms']);
        self::assertNotNull($active['last_heartbeat_at']);

        $replica = array_values(array_filter($status, static fn (array $row): bool => $row['role'] === 'replica' && $row['name'] === 'magds_replica'))[0] ?? null;
        self::assertNotNull($replica);
        self::assertTrue($replica['healthy']);
        self::assertTrue($replica['active']);
        self::assertIsNumeric($replica['score']);
    }
}


