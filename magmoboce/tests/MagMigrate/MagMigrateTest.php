<?php

declare(strict_types=1);

namespace Tests\MagMigrate;

use MoBo\MagMigrate\MagMigrate;
use MoBo\MagMigrate\MigrationLoader;
use MoBo\MagMigrate\MigrationRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class MagMigrateTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = __DIR__ . '/../Fixtures/Migrations/magds';
    }

    public function testMigrateUpAndStatus(): void
    {
        $manager = $this->makeManager();

        $initialStatus = $manager->status('magds');
        self::assertCount(2, $initialStatus);
        self::assertFalse($initialStatus[0]['applied']);

        $applied = $manager->migrateUp('magds');
        self::assertCount(2, $applied);
        self::assertSame('20250101000001', $applied[0]->id());

        $statusAfter = $manager->status('magds');
        self::assertTrue($statusAfter[0]['applied']);
        self::assertTrue($statusAfter[1]['applied']);

        $pdo = $this->getPdo($manager);
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='people'");
        self::assertNotFalse($result);
        self::assertNotFalse($result->fetch());
    }

    public function testMigrateDownRollsBackChanges(): void
    {
        $manager = $this->makeManager();
        $manager->migrateUp('magds');

        $rolledBack = $manager->migrateDown('magds', steps: 1);
        self::assertCount(1, $rolledBack);
        self::assertSame('20250102000000', $rolledBack[0]->id());

        $pdo = $this->getPdo($manager);
        $result = $pdo->query("PRAGMA table_info(people)");
        $columns = [];
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[] = $row['name'];
        }
        self::assertSame(['id', 'name'], $columns);
    }

    public function testBaselineMarksMigrations(): void
    {
        $manager = $this->makeManager();

        $marked = $manager->baseline('magds', '20250101000001');
        self::assertCount(1, $marked);

        $status = $manager->status('magds');
        self::assertTrue($status[0]['applied']);
        self::assertFalse($status[1]['applied']);
    }

    private function makeManager(): MagMigrate
    {
        $pdo = new PDO('sqlite::memory:');
        $loader = new MigrationLoader(['magds' => $this->fixtureDir]);
        $repository = new MigrationRepository($pdo);

        return new MagMigrate($pdo, $loader, $repository);
    }

    private function getPdo(MagMigrate $manager): PDO
    {
        $ref = new \ReflectionObject($manager);
        $prop = $ref->getProperty('connection');
        $prop->setAccessible(true);
        /** @var PDO $pdo */
        $pdo = $prop->getValue($manager);
        return $pdo;
    }
}
