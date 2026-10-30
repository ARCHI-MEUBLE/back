<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Lib\ImageUrl;

final class CartSampleRepository
{
    public function __construct(private readonly Connection $db) {}

    public function items(int $customerId, string $host): array
    {
        $rows = $this->db->query(
            'SELECT csi.id, csi.sample_color_id, csi.quantity, csi.created_at,
                    sc.name as color_name, sc.hex, sc.image_url, sc.price_per_m2, sc.unit_price,
                    st.name as type_name, st.material, st.description as type_description
             FROM cart_sample_items csi
             JOIN sample_colors sc ON csi.sample_color_id = sc.id
             JOIN sample_types st ON sc.type_id = st.id
             WHERE csi.customer_id = ?
             ORDER BY csi.created_at DESC',
            [$customerId],
        );
        return array_map(static fn(array $row): array => ImageUrl::decorate($row, $host), $rows);
    }

    public function add(int $customerId, int $sampleColorId, int $quantity): int
    {
        $existing = $this->db->queryOne(
            'SELECT id FROM cart_sample_items WHERE customer_id = ? AND sample_color_id = ?',
            [$customerId, $sampleColorId],
        );
        if ($existing !== null) {
            throw new DomainException('Cet échantillon est déjà dans votre panier');
        }
        return $this->db->insertReturningId(
            'INSERT INTO cart_sample_items (customer_id, sample_color_id, quantity) VALUES (?, ?, ?) RETURNING id',
            [$customerId, $sampleColorId, $quantity],
        );
    }

    public function updateQuantity(int $customerId, int $itemId, int $quantity): void
    {
        $this->db->execute(
            'UPDATE cart_sample_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND customer_id = ?',
            [max(1, $quantity), $itemId, $customerId],
        );
    }

    public function remove(int $customerId, int $itemId): void
    {
        $this->db->execute('DELETE FROM cart_sample_items WHERE id = ? AND customer_id = ?', [$itemId, $customerId]);
    }
}
