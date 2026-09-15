<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Db\Connection;

final class CartCatalogueRepository
{
    public function __construct(private readonly Connection $db) {}

    public function items(int $customerId): array
    {
        return $this->db->query(
            'SELECT cci.id, cci.catalogue_item_id, cci.variation_id, cci.quantity,
                    ci.name, ci.unit_price, ci.unit, ci.image_url as item_image,
                    civ.color_name as variation_name, civ.image_url as variation_image
             FROM cart_catalogue_items cci
             JOIN catalogue_items ci ON cci.catalogue_item_id = ci.id
             LEFT JOIN catalogue_item_variations civ ON cci.variation_id = civ.id
             WHERE cci.customer_id = ?',
            [$customerId],
        );
    }

    public function add(int $customerId, int $catalogueItemId, ?int $variationId, int $quantity): void
    {
        $existing = $variationId === null
            ? $this->db->queryOne(
                'SELECT id, quantity FROM cart_catalogue_items WHERE customer_id = ? AND catalogue_item_id = ? AND variation_id IS NULL',
                [$customerId, $catalogueItemId],
            )
            : $this->db->queryOne(
                'SELECT id, quantity FROM cart_catalogue_items WHERE customer_id = ? AND catalogue_item_id = ? AND variation_id = ?',
                [$customerId, $catalogueItemId, $variationId],
            );
        if ($existing !== null) {
            $this->db->execute(
                'UPDATE cart_catalogue_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$existing['quantity'] + $quantity, $existing['id']],
            );
            return;
        }
        $this->db->execute(
            'INSERT INTO cart_catalogue_items (customer_id, catalogue_item_id, variation_id, quantity) VALUES (?, ?, ?, ?)',
            [$customerId, $catalogueItemId, $variationId, $quantity],
        );
    }

    public function applyQuantity(int $customerId, int $id, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->db->execute('DELETE FROM cart_catalogue_items WHERE id = ? AND customer_id = ?', [$id, $customerId]);
            return;
        }
        $this->db->execute(
            'UPDATE cart_catalogue_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND customer_id = ?',
            [$quantity, $id, $customerId],
        );
    }

    public function remove(int $customerId, ?int $id): void
    {
        if ($id !== null) {
            $this->db->execute('DELETE FROM cart_catalogue_items WHERE id = ? AND customer_id = ?', [$id, $customerId]);
            return;
        }
        $this->db->execute('DELETE FROM cart_catalogue_items WHERE customer_id = ?', [$customerId]);
    }
}
