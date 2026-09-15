<?php

declare(strict_types=1);

namespace App\Console;

use App\Config\Settings;
use App\Db\Connection;
use App\Db\Migrator;

final class MigrateCommand
{
    public function __construct(private readonly Settings $settings) {}

    public function run(string $action): int
    {
        $migrator = new Migrator(Connection::fromUrl($this->settings->databaseUrl), $this->settings->rootDir . '/src/Db/migrations');
        if ($action === 'migrate:status') {
            foreach ($migrator->status() as $row) {
                $appliedAt = is_array($row) && is_string($row['applied_at'] ?? null) ? $row['applied_at'] : 'pending';
                $name = is_array($row) && is_string($row['name'] ?? null) ? $row['name'] : '?';
                fwrite(STDOUT, sprintf("%-40s %s\n", $name, $appliedAt));
            }
            return 0;
        }
        $applied = $migrator->migrate();
        fwrite(STDOUT, $applied === [] ? "Nothing to migrate\n" : sprintf("Applied migrations: %s\n", implode(', ', $applied)));
        return 0;
    }
}
