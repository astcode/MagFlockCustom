<?php

declare(strict_types=1);

namespace Tests\Components;

use Components\MagDB\MagDB;
use MoBo\Kernel;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Kernel\KernelHarness;

final class MagDBSchemaTest extends TestCase
{
    private array $databaseConfig;
    private ?Kernel $kernel = null;
    private ?string $originalLogLevel = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLogLevel = getenv('LOG_LEVEL') !== false ? getenv('LOG_LEVEL') : null;
        putenv('LOG_LEVEL=error');
        $_ENV['LOG_LEVEL'] = 'error';

        ob_start();
        try {
            $this->kernel = KernelHarness::bootWith(
                [],
                true,
                ['MagDB'],
                __DIR__ . '/../../config/mobo.php'
            );
            ob_end_clean();
            $this->databaseConfig = (array) $this->kernel->getConfig()->get('database', []);
        } catch (PDOException $e) {
            ob_end_clean();
            $this->markTestSkipped('MagDS database not reachable: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->kernel !== null) {
            ob_start();
            KernelHarness::shutdown($this->kernel);
            ob_end_clean();
        }
        $this->kernel = null;

        if ($this->originalLogLevel === null) {
            putenv('LOG_LEVEL');
            unset($_ENV['LOG_LEVEL']);
        } else {
            putenv('LOG_LEVEL=' . $this->originalLogLevel);
            $_ENV['LOG_LEVEL'] = $this->originalLogLevel;
        }

        parent::tearDown();
    }

    public function testListTablesAndCreateFakeDatabase(): void
    {
        $magdb = $this->kernel?->get('MagDB');
        self::assertInstanceOf(MagDB::class, $magdb);

        $pdo = $magdb->connection($this->databaseConfig['default'] ?? null);
        self::assertInstanceOf(PDO::class, $pdo, 'Default MagDB connection not available.');

        $tables = $pdo->query(
            "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename"
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertIsArray($tables);

        $defaultKey = $this->databaseConfig['default'] ?? 'magdsdb';
        $defaultConnection = $this->databaseConfig['connections'][$defaultKey] ?? null;
        if ($defaultConnection === null) {
            $this->markTestSkipped('Default MagDB connection configuration missing.');
        }

        $fakeDbName = 'mobofake';
        $created = false;

        try {
            $pdo->exec("CREATE DATABASE {$fakeDbName}");
            $created = true;
        } catch (PDOException $e) {
            $this->markTestSkipped('Unable to create fake database: ' . $e->getMessage());
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $defaultConnection['host'] ?? '127.0.0.1',
            $defaultConnection['port'] ?? '5432',
            $fakeDbName
        );

        $fakePdo = new PDO(
            $dsn,
            $defaultConnection['username'] ?? null,
            $defaultConnection['password'] ?? null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $fakePdo->exec('CREATE TABLE customers (id SERIAL PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL)');
        $fakePdo->exec("
            INSERT INTO customers (name, email) VALUES
            ('Ada Lovelace', 'ada@example.com'),
            ('Alan Turing', 'alan@example.com'),
            ('Grace Hopper', 'grace@example.com')
        ");

        $rows = $fakePdo->query('SELECT id, name, email FROM customers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(3, $rows);

        // Leave database for manual inspection. Caller can drop manually when done.
        // Uncomment the lines below to restore automatic cleanup:
        $fakePdo = null;
        if ($created) {
            $pdo->exec("DROP DATABASE {$fakeDbName}");
        }
    }
}


