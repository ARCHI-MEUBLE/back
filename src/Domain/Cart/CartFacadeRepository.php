<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Db\Connection;
use App\Lib\Json;

final class CartFacadeRepository
{
    public function __construct(private readonly Connection $db) {}

    public function items(int $customerId): array
    {
        $rows = $this->db->query(
            'SELECT id, config_data, quantity, unit_price, created_at FROM facade_cart_items WHERE customer_id = ? ORDER BY created_at DESC',
            [$customerId],
        );
        return array_map(static function (array $row): array {
            $row['config'] = json_decode((string) $row['config_data'], true);
            unset($row['config_data']);
            return $row;
        }, $rows);
    }

    public function total(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += ((float) $item['unit_price']) * ((int) $item['quantity']);
        }
        return $total;
    }

    public function add(int $customerId, array $config, float $price, int $quantity): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO facade_cart_items (customer_id, config_data, quantity, unit_price) VALUES (?, ?, ?, ?) RETURNING id',
            [$customerId, Json::encode($config), $quantity, $price],
        );
    }

    public function updateQuantity(int $customerId, int $id, int $quantity): void
    {
        $this->db->execute('UPDATE facade_cart_items SET quantity = ? WHERE id = ? AND customer_id = ?', [$quantity, $id, $customerId]);
    }

    public function remove(int $customerId, ?int $id): void
    {
        if ($id !== null) {
            $this->db->execute('DELETE FROM facade_cart_items WHERE id = ? AND customer_id = ?', [$id, $customerId]);
            return;
        }
        $this->db->execute('DELETE FROM facade_cart_items WHERE customer_id = ?', [$customerId]);
    }
}
