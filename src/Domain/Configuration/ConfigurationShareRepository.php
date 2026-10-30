<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Db\Connection;
use RuntimeException;

final class ConfigurationShareRepository
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly Connection $db) {}

    public function save(string $configuratorId, string $prompt, string $configJson): string
    {
        $hash = hash('sha256', $configuratorId . "\n" . $prompt . "\n" . $configJson);
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $existing = $this->db->scalar('SELECT code FROM configuration_shares WHERE content_hash = ?', [$hash]);
            if (is_string($existing)) {
                return $existing;
            }
            $code = $this->db->scalar(
                'INSERT INTO configuration_shares (code, configurator_id, prompt, config_data, content_hash)
                 VALUES (?, ?, ?, CAST(? AS JSONB), ?) ON CONFLICT DO NOTHING RETURNING code',
                [ShareCode::generate(), $configuratorId, $prompt, $configJson, $hash],
            );
            if (is_string($code)) {
                return $code;
            }
        }
        throw new RuntimeException('Unable to allocate a configuration share code');
    }

    public function open(string $code): ?array
    {
        $row = $this->db->queryOne(
            'UPDATE configuration_shares SET last_opened_at = CURRENT_TIMESTAMP WHERE code = ?
             RETURNING code, configurator_id, prompt, config_data',
            [$code],
        );
        if ($row === null) {
            return null;
        }
        $config = json_decode((string) $row['config_data'], true);
        return [
            'code' => (string) $row['code'],
            'configurator_id' => (string) $row['configurator_id'],
            'prompt' => (string) $row['prompt'],
            'config_data' => is_array($config) ? $config : [],
        ];
    }
}
