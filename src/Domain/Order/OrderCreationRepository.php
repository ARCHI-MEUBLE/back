<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Db\Connection;
use App\Domain\Cart\CartRepository;

final class OrderCreationRepository
{
    public function __construct(private readonly Connection $db, private readonly CartRepository $cart) {}

    public function cartConfigItems(int $customerId): array
    {
        return $this->cart->items($customerId);
    }

    public function cartSampleItems(int $customerId): array
    {
        return $this->db->query(
            'SELECT csi.id, csi.sample_color_id, csi.quantity,
                    sc.name as color_name, sc.hex, sc.image_url, sc.unit_price, sc.price_per_m2,
                    st.name as type_name, st.material
             FROM cart_sample_items csi
             JOIN sample_colors sc ON csi.sample_color_id = sc.id
             JOIN sample_types st ON sc.type_id = st.id
             WHERE csi.customer_id = ?',
            [$customerId],
        );
    }

    public function cartCatalogueItems(int $customerId): array
    {
        return $this->db->query(
            'SELECT cci.id, cci.catalogue_item_id, cci.variation_id, cci.quantity,
                    ci.name, ci.unit_price, civ.color_name as variation_name,
                    COALESCE(civ.image_url, ci.image_url) as image_url
             FROM cart_catalogue_items cci
             JOIN catalogue_items ci ON cci.catalogue_item_id = ci.id
             LEFT JOIN catalogue_item_variations civ ON cci.variation_id = civ.id
             WHERE cci.customer_id = ?',
            [$customerId],
        );
    }

    public function cartFacadeItems(int $customerId): array
    {
        return $this->db->query('SELECT * FROM facade_cart_items WHERE customer_id = ?', [$customerId]);
    }

    public function insertOrder(int $customerId, string $orderNumber, float $total, string $shippingAddress, string $billingAddress, string $paymentMethod, ?string $notes, string $status): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO orders (customer_id, order_number, total_amount, shipping_address, billing_address, payment_method, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id',
            [$customerId, $orderNumber, $total, $shippingAddress, $billingAddress, $paymentMethod, $notes, $status],
        );
    }

    public function insertConfigItem(int $orderId, array $item): void
    {
        $unitPrice = $item['configuration']['price'];
        $quantity = $item['quantity'];
        $this->db->execute(
            'INSERT INTO order_items (order_id, configuration_id, prompt, config_data, glb_url, quantity, unit_price, total_price)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$orderId, $item['configuration_id'], $item['configuration']['prompt'], json_encode($item['configuration']['config_data']), $item['configuration']['glb_url'], $quantity, $unitPrice, $unitPrice * $quantity],
        );
        $this->db->execute("UPDATE configurations SET status = 'en_commande' WHERE id = ?", [$item['configuration_id']]);
    }

    public function insertSampleItem(int $orderId, array $sample): void
    {
        $this->db->execute(
            'INSERT INTO order_sample_items (order_id, sample_color_id, sample_name, sample_type_name, material, image_url, hex, quantity, price)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$orderId, $sample['sample_color_id'], $sample['color_name'], $sample['type_name'], $sample['material'], $sample['image_url'], $sample['hex'], $sample['quantity'], $sample['unit_price'] ?? 0.00],
        );
    }

    public function insertCatalogueItem(int $orderId, array $item): void
    {
        $this->db->execute(
            'INSERT INTO order_catalogue_items (order_id, catalogue_item_id, variation_id, product_name, variation_name, image_url, quantity, unit_price, total_price)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$orderId, $item['catalogue_item_id'], $item['variation_id'] ?? null, $item['name'] ?? 'Article', $item['variation_name'] ?? null, $item['image_url'] ?? null, $item['quantity'], $item['unit_price'], $item['unit_price'] * $item['quantity']],
        );
    }

    public function insertFacadeItem(int $orderId, array $facade): void
    {
        $this->db->execute(
            'INSERT INTO order_facade_items (order_id, config_data, quantity, unit_price, total_price)
             VALUES (?, ?, ?, ?, ?)',
            [$orderId, $facade['config_data'], $facade['quantity'], $facade['unit_price'], $facade['unit_price'] * $facade['quantity']],
        );
    }

    public function clearCart(int $customerId): void
    {
        $this->cart->clear($customerId);
        $this->db->execute('DELETE FROM cart_sample_items WHERE customer_id = ?', [$customerId]);
        $this->db->execute('DELETE FROM cart_catalogue_items WHERE customer_id = ?', [$customerId]);
        $this->db->execute('DELETE FROM facade_cart_items WHERE customer_id = ?', [$customerId]);
    }
}
