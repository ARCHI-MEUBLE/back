<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Db\Connection;
use App\Domain\Shared\DomainException;

final class OrderRepository
{
    private const VALID_STATUSES = ['pending', 'confirmed', 'in_production', 'shipped', 'delivered', 'cancelled'];

    public function __construct(private readonly Connection $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public function findByStripeIntentId(string $intentId): ?array
    {
        return $this->db->queryOne('SELECT id, customer_id, payment_status FROM orders WHERE stripe_payment_intent_id = ?', [$intentId]);
    }

    public function itemsFor(int $orderId): array
    {
        return $this->db->query('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    public function samplesFor(int $orderId): array
    {
        return $this->db->query('SELECT * FROM order_sample_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    public function catalogueItemsFor(int $orderId): array
    {
        return $this->db->query(
            'SELECT oci.*, ci.name as item_name, civ.color_name as variation_name,
                    COALESCE(oci.image_url, civ.image_url, ci.image_url) as image_url
             FROM order_catalogue_items oci
             LEFT JOIN catalogue_items ci ON oci.catalogue_item_id = ci.id
             LEFT JOIN catalogue_item_variations civ ON oci.variation_id = civ.id
             WHERE oci.order_id = ?
             ORDER BY oci.id',
            [$orderId],
        );
    }

    public function facadeItemsFor(int $orderId): array
    {
        return $this->db->query('SELECT * FROM order_facade_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    public function forCustomer(int $customerId, int $limit, int $offset): array
    {
        return $this->db->query(
            'SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$customerId, $limit, $offset],
        );
    }

    public function all(?string $status, int $limit, int $offset): array
    {
        return $status === null
            ? $this->db->query('SELECT o.*, c.email as customer_email, c.first_name, c.last_name FROM orders o JOIN customers c ON o.customer_id = c.id ORDER BY o.created_at DESC LIMIT ? OFFSET ?', [$limit, $offset])
            : $this->db->query('SELECT o.*, c.email as customer_email, c.first_name, c.last_name FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.status = ? ORDER BY o.created_at DESC LIMIT ? OFFSET ?', [$status, $limit, $offset]);
    }

    public function countByStatus(?string $status): int
    {
        return (int) ($status === null
            ? $this->db->scalar('SELECT COUNT(*) FROM orders')
            : $this->db->scalar('SELECT COUNT(*) FROM orders WHERE status = ?', [$status]));
    }

    public function updateStatus(int $orderId, string $status, ?string $adminNotes): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new DomainException('Erreur serveur', 500);
        }
        if ($status === 'cancelled') {
            $this->db->execute("UPDATE payment_links SET status = 'revoked' WHERE order_id = ? AND status = 'active'", [$orderId]);
            foreach ($this->itemsFor($orderId) as $item) {
                if (isset($item['configuration_id'])) {
                    $this->db->execute("UPDATE configurations SET status = 'termine' WHERE id = ?", [$item['configuration_id']]);
                }
            }
        }
        $adminNotes === null
            ? $this->db->execute('UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$status, $orderId])
            : $this->db->execute('UPDATE orders SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$status, $adminNotes, $orderId]);
    }

    public function delete(int $orderId): void
    {
        foreach ($this->itemsFor($orderId) as $item) {
            if (isset($item['configuration_id'])) {
                $this->db->execute("UPDATE configurations SET status = 'termine' WHERE id = ?", [$item['configuration_id']]);
            }
        }
        $this->db->execute('DELETE FROM order_items WHERE order_id = ?', [$orderId]);
        $this->db->execute('DELETE FROM order_sample_items WHERE order_id = ?', [$orderId]);
        $this->db->execute('DELETE FROM order_catalogue_items WHERE order_id = ?', [$orderId]);
        $this->db->execute('DELETE FROM order_facade_items WHERE order_id = ?', [$orderId]);
        $this->db->execute("UPDATE payment_links SET status = 'revoked' WHERE order_id = ? AND status = 'active'", [$orderId]);
        $this->db->execute('DELETE FROM orders WHERE id = ?', [$orderId]);
    }
}
