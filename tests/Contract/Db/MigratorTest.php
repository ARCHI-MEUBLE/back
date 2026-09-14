<?php

declare(strict_types=1);

namespace Tests\Contract\Db;

use App\Db\Connection;
use App\Db\Migrator;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseUrl;

final class MigratorTest extends TestCase
{
    private Connection $db;
    private string $dir;

    protected function setUp(): void
    {
        $this->db = Connection::fromUrl(DatabaseUrl::fromEnv());
        $this->db->exec('DROP SCHEMA IF EXISTS migrator_test CASCADE');
        $this->db->exec('CREATE SCHEMA migrator_test');
        $this->db->exec('SET search_path TO migrator_test');
        $this->dir = sys_get_temp_dir() . '/migrations-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir . '/0001_first.sql', 'CREATE TABLE widgets (id SERIAL PRIMARY KEY, name TEXT NOT NULL);');
        file_put_contents($this->dir . '/0002_second.sql', "ALTER TABLE widgets ADD COLUMN color TEXT;\nINSERT INTO widgets (name, color) VALUES ('a', 'red');");
    }

    protected function tearDown(): void
    {
        $this->db->exec('DROP SCHEMA IF EXISTS migrator_test CASCADE');
        $files = glob($this->dir . '/*.sql');
        array_map('unlink', $files === false ? [] : $files);
        rmdir($this->dir);
    }

    public function testAppliesPendingMigrationsOnceInOrder(): void
    {
        $migrator = new Migrator($this->db, $this->dir);

        self::assertSame([1, 2], $migrator->migrate());
        self::assertSame([], $migrator->migrate());
        self::assertSame('1', (string) $this->db->scalar('SELECT count(*) FROM widgets'));
        $status = $migrator->status();
        self::assertCount(2, $status);
        self::assertSame('0002_second.sql', $status[1]['name']);
        self::assertNotNull($status[1]['applied_at']);
    }

    public function testFailedMigrationIsRolledBackAndAbortsStartup(): void
    {
        file_put_contents($this->dir . '/0003_broken.sql', "INSERT INTO widgets (name) VALUES ('b');\nSELECT * FROM missing_table;");
        $migrator = new Migrator($this->db, $this->dir);

        $threw = false;
        try {
            $migrator->migrate();
        } catch (\PDOException) {
            $threw = true;
        }
        self::assertTrue($threw);

        $this->db->exec('SET search_path TO migrator_test');
        self::assertSame('1', (string) $this->db->scalar('SELECT count(*) FROM widgets'));
        self::assertNull($this->db->scalar('SELECT version FROM schema_migrations WHERE version = 3'));
        self::assertArrayHasKey(3, $migrator->pending());
    }

    public function testBaselineAppliesOnEmptyDatabaseAndIsIdempotent(): void
    {
        $migrator = new Migrator($this->db, dirname(__DIR__, 3) . '/src/Db/migrations');

        self::assertSame([1], $migrator->migrate());
        self::assertSame([], $migrator->migrate());
        self::assertSame('47', (string) $this->db->scalar("SELECT count(*) FROM information_schema.tables WHERE table_schema = 'migrator_test' AND table_name <> 'schema_migrations'"));
        self::assertSame('4', (string) $this->db->scalar('SELECT count(*) FROM email_templates'));
    }
}
