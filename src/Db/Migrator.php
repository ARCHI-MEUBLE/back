<?php

declare(strict_types=1);

namespace App\Db;

use RuntimeException;

final class Migrator
{
    private const LOCK_KEY = 7411;

    public function __construct(private readonly Connection $db, private readonly string $directory) {}

    public function migrate(): array
    {
        $this->db->exec('SELECT pg_advisory_lock(' . self::LOCK_KEY . ')');
        try {
            $this->ensureTable();
            $applied = [];
            foreach ($this->pending() as $version => $file) {
                $this->apply($version, $file);
                $applied[] = $version;
            }
            return $applied;
        } finally {
            $this->db->exec('SELECT pg_advisory_unlock(' . self::LOCK_KEY . ')');
        }
    }

    public function status(): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $rows = [];
        foreach ($this->files() as $version => $file) {
            $rows[] = ['version' => $version, 'name' => basename($file), 'applied_at' => $applied[$version] ?? null];
        }
        return $rows;
    }

    public function pending(): array
    {
        $applied = $this->applied();
        return array_filter($this->files(), static fn(int $version): bool => !isset($applied[$version]), ARRAY_FILTER_USE_KEY);
    }

    private function apply(int $version, string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Cannot read migration ' . $file);
        }
        $this->db->transaction(function (Connection $db) use ($version, $file, $sql): void {
            $db->exec($sql);
            $db->execute(
                'INSERT INTO schema_migrations (version, name, checksum) VALUES (?, ?, ?)',
                [$version, basename($file), hash('sha256', $sql)],
            );
        });
    }

    private function files(): array
    {
        $files = glob($this->directory . '/*.sql');
        $result = [];
        foreach ($files === false ? [] : $files as $file) {
            if (preg_match('/^(\d{4})_/', basename($file), $matches) !== 1) {
                throw new RuntimeException('Migration file name must start with 4 digits: ' . basename($file));
            }
            $result[(int) $matches[1]] = $file;
        }
        ksort($result);
        return $result;
    }

    private function applied(): array
    {
        $applied = [];
        foreach ($this->db->query('SELECT version, applied_at FROM schema_migrations') as $row) {
            if (is_array($row) && isset($row['version']) && is_numeric($row['version'])) {
                $applied[(int) $row['version']] = is_string($row['applied_at']) ? $row['applied_at'] : '';
            }
        }
        return $applied;
    }

    private function ensureTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                checksum TEXT NOT NULL,
                applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )',
        );
    }
}
