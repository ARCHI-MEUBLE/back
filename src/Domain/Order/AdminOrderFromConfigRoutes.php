<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Db\Connection;
use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class AdminOrderFromConfigRoutes
{
    public function __construct(
        private readonly Connection $db,
        private readonly AdminNotificationRepository $adminNotifications,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/create-order-from-config', [new AdminGuard()], ErrorStyle::Success)
            ->post(null, fn(Request $r, Session $s): Response => $this->create($r, $s));
    }

    private function create(Request $r, Session $s): Response
    {
        $data = $r->jsonOrEmpty();
        if (!isset($data['configuration_id'])) {
            throw new DomainException('ID de configuration manquant');
        }
        $configId = (int) $data['configuration_id'];
        $config = $this->db->queryOne(
            'SELECT c.*, cust.id as customer_id, cust.email as customer_email, cust.first_name, cust.last_name,
                    cust.phone, cust.address, cust.city, cust.postal_code, cust.country
             FROM configurations c
             LEFT JOIN customers cust ON CAST(c.user_id AS INTEGER) = cust.id
             WHERE c.id = ?',
            [$configId],
        );
        if ($config === null) {
            throw new NotFoundException('Configuration introuvable');
        }
        if (in_array($config['status'], ['en_commande', 'payee'], true)) {
            throw new DomainException('Cette configuration a déjà été transformée en commande');
        }
        if (!$config['customer_id']) {
            throw new DomainException('Aucun client associé à cette configuration');
        }
        $shippingAddress = trim(($config['address'] ?? '') . ', ' . ($config['city'] ?? '') . ', ' . ($config['postal_code'] ?? '') . ', ' . ($config['country'] ?? 'France'));
        $orderNumber = 'ORD-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $adminNotes = "Commande créée depuis configuration #{$configId} par " . $s->string('admin_email');
        $orderId = $this->db->insertReturningId(
            "INSERT INTO orders (customer_id, order_number, total_amount, shipping_address, billing_address, payment_method, payment_status, status, notes, admin_notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'card', 'pending', 'pending', ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id",
            [$config['customer_id'], $orderNumber, $config['price'], $shippingAddress, $shippingAddress, "Commande créée par l'admin après validation téléphonique", $adminNotes],
        );
        $this->db->execute(
            "INSERT INTO order_items (order_id, configuration_id, prompt, config_data, glb_url, quantity, unit_price, total_price, production_status, created_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, 'pending', CURRENT_TIMESTAMP)",
            [$orderId, $configId, $config['prompt'], $config['config_string'], $config['glb_url'], $config['price'], $config['price']],
        );
        $this->db->execute("UPDATE configurations SET status = 'en_commande' WHERE id = ?", [$configId]);
        $this->adminNotifications->notifyAllAdmins('new_order', "Nouvelle commande #{$orderNumber} créée depuis config", $orderId);
        return Response::json([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $config['price'],
                'customer_email' => $config['customer_email'],
                'customer_name' => trim($config['first_name'] . ' ' . $config['last_name']),
                'payment_link' => null,
            ],
            'message' => 'Commande créée avec succès. Vous pouvez maintenant générer le lien de paiement.',
        ], 201);
    }
}
