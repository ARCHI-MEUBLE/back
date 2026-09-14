<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;

final class CartRepository
{
    public function __construct(private readonly Connection $db) {}

    public function items(int $customerId): array
    {
        $rows = $this->db->query(
            'SELECT ci.id, ci.configuration_id, ci.quantity, ci.added_at,
                    c.id as config_id, c.prompt, c.price, c.glb_url, c.config_string, c.created_at as config_created_at
             FROM cart_items ci
             JOIN configurations c ON ci.configuration_id = c.id
             WHERE ci.customer_id = ?
             ORDER BY ci.added_at DESC',
            [$customerId],
        );
        return array_map(static function (array $row): array {
            $configData = json_decode((string) $row['config_string'], true) ?? [];
            $configData = is_array($configData) ? $configData : [];
            return [
                'id' => $row['id'],
                'configuration_id' => $row['configuration_id'],
                'quantity' => $row['quantity'],
                'added_at' => $row['added_at'],
                'configuration' => [
                    'id' => $row['config_id'],
                    'name' => $configData['name'] ?? 'Configuration sans nom',
                    'prompt' => $row['prompt'],
                    'price' => $row['price'],
                    'glb_url' => $row['glb_url'],
                    'thumbnail_url' => $configData['thumbnail_url'] ?? null,
                    'config_data' => $configData,
                    'created_at' => $row['config_created_at'],
                ],
            ];
        }, $rows);
    }

    public function total(int $customerId): float
    {
        $total = 0.0;
        foreach ($this->items($customerId) as $item) {
            $total += ((float) $item['configuration']['price']) * ((int) $item['quantity']);
        }
        return $total;
    }

    public function count(int $customerId): int
    {
        return (int) ($this->db->scalar('SELECT SUM(quantity) FROM cart_items WHERE customer_id = ?', [$customerId]) ?? 0);
    }

    public function addItem(int $customerId, int $configurationId, int $quantity): void
    {
        $config = $this->db->queryOne('SELECT status FROM configurations WHERE id = ?', [$configurationId]);
        if ($config === null) {
            throw new NotFoundException('Configuration introuvable');
        }
        if ($config['status'] !== 'validee') {
            throw new DomainException("Cette configuration doit être validée par un menuisier avant d'être ajoutée au panier.");
        }
        $existing = $this->db->queryOne(
            'SELECT id, quantity FROM cart_items WHERE customer_id = ? AND configuration_id = ?',
            [$customerId, $configurationId],
        );
        if ($existing !== null) {
            $this->db->execute(
                'UPDATE cart_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$existing['quantity'] + $quantity, $existing['id']],
            );
            return;
        }
        $this->db->execute(
            'INSERT INTO cart_items (customer_id, configuration_id, quantity) VALUES (?, ?, ?)',
            [$customerId, $configurationId, $quantity],
        );
    }

    public function updateQuantity(int $customerId, int $configurationId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($customerId, $configurationId);
            return;
        }
        $this->db->execute(
            'UPDATE cart_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE customer_id = ? AND configuration_id = ?',
            [$quantity, $customerId, $configurationId],
        );
    }

    public function removeItem(int $customerId, int $configurationId): void
    {
        $this->db->execute('DELETE FROM cart_items WHERE customer_id = ? AND configuration_id = ?', [$customerId, $configurationId]);
    }

    public function clear(int $customerId): void
    {
        $this->db->execute('DELETE FROM cart_items WHERE customer_id = ?', [$customerId]);
    }
}
