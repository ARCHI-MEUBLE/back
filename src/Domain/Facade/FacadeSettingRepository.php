<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Db\Connection;

final class FacadeSettingRepository
{
    public function __construct(private readonly Connection $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT * FROM facade_settings ORDER BY setting_key');
    }

    public function findByKey(string $key): ?array
    {
        return $this->db->queryOne('SELECT * FROM facade_settings WHERE setting_key = ?', [$key]);
    }

    public function updateValue(string $key, string $value): void
    {
        $this->db->execute('UPDATE facade_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?', [$value, $key]);
    }
}
