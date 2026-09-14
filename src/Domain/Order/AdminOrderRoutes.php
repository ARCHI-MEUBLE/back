<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Db\Connection;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Notification\NotificationRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\Mail\LegacyEmailGateway;

final class AdminOrderRoutes
{
    private const VALID_STATUSES = ['pending', 'confirmed', 'paid', 'in_production', 'shipped', 'delivered', 'cancelled', 'refunded'];

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly CustomerRepository $customers,
        private readonly Connection $db,
        private readonly NotificationRepository $notifications,
        private readonly LegacyEmailGateway $mail,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('admin/orders', [new AdminGuard()]);
        $script->get(null, fn(Request $r): Response => $this->get($r));
        $script->put(null, fn(Request $r): Response => $this->put($r));
    }

    private function get(Request $r): Response
    {
        $id = $r->queryString('id');
        if ($id !== null) {
            $orderId = filter_var($id, FILTER_VALIDATE_INT);
            if ($orderId === false || $orderId <= 0) {
                throw new DomainException('ID de commande invalide');
            }
            $order = $this->orders->findById($orderId);
            if ($order === null) {
                throw new NotFoundException('Commande non trouvée');
            }
            $order['customer'] = $this->customers->findRawById((int) $order['customer_id']);
            $order['items'] = $this->orders->itemsFor($orderId);
            $order['samples'] = $this->orders->samplesFor($orderId);
            $order['catalogue_items'] = $this->orders->catalogueItemsFor($orderId);
            $order['facade_items'] = $this->orders->facadeItemsFor($orderId);
            return Response::json(['order' => OrderMapper::toFrontend($order)]);
        }
        $status = $r->queryString('status');
        $limit = (int) ($r->queryString('limit') ?? 100);
        $offset = (int) ($r->queryString('offset') ?? 0);
        $orders = array_map(function (array $order): array {
            $order['customer'] = $this->customers->findRawById((int) $order['customer_id']);
            $order['samples_count'] = count($this->orders->samplesFor((int) $order['id']));
            return OrderMapper::toFrontend($order);
        }, $this->orders->all($status, $limit, $offset));
        return Response::json([
            'orders' => $orders,
            'total' => $this->orders->countByStatus($status),
            'stats' => [
                'pending' => $this->orders->countByStatus('pending'),
                'confirmed' => $this->orders->countByStatus('confirmed'),
                'in_production' => $this->orders->countByStatus('in_production'),
                'shipped' => $this->orders->countByStatus('shipped'),
                'delivered' => $this->orders->countByStatus('delivered'),
                'cancelled' => $this->orders->countByStatus('cancelled'),
            ],
        ]);
    }

    private function put(Request $r): Response
    {
        $data = $r->json();
        if (!isset($data['order_id']) || !isset($data['status'])) {
            throw new DomainException('order_id et status requis');
        }
        $orderId = filter_var($data['order_id'], FILTER_VALIDATE_INT);
        if ($orderId === false || $orderId <= 0) {
            throw new DomainException('ID de commande invalide');
        }
        $newStatus = trim((string) $data['status']);
        if (!in_array($newStatus, self::VALID_STATUSES, true)) {
            return Response::json(['error' => 'Statut invalide', 'valid_statuses' => self::VALID_STATUSES], 400);
        }
        $adminNotes = isset($data['admin_notes']) ? trim(strip_tags((string) $data['admin_notes'])) : null;
        if ($adminNotes !== null && strlen($adminNotes) > 2000) {
            $adminNotes = substr($adminNotes, 0, 2000);
        }
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new NotFoundException('Commande non trouvée');
        }
        $oldStatus = $order['status'] ?? 'unknown';
        $this->orders->updateStatus($orderId, $newStatus, $adminNotes);
        if ($newStatus === 'cancelled') {
            $this->notifyCancellation((int) $order['customer_id'], $order['order_number']);
        }
        if (isset($order['customer_id'])) {
            $this->notifyCustomer((int) $order['customer_id'], $orderId, $order['order_number'], $oldStatus, $newStatus);
        }
        return Response::json(['success' => true, 'message' => 'Statut mis à jour', 'order_id' => $orderId, 'old_status' => $oldStatus, 'new_status' => $newStatus]);
    }

    private function notifyCancellation(int $customerId, string $orderNumber): void
    {
        try {
            $customer = $this->db->queryOne('SELECT email, first_name FROM customers WHERE id = ?', [$customerId]);
            if ($customer !== null) {
                $this->mail->sendOrderCancelled($customer['email'], $customer['first_name'], $orderNumber);
            }
        } catch (\Throwable $emailError) {
            error_log("Erreur lors de l'envoi de l'email d'annulation: " . $emailError->getMessage());
        }
    }

    private function notifyCustomer(int $customerId, int $orderId, string $orderNumber, string $oldStatus, string $newStatus): void
    {
        $labels = ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'in_production' => 'En production', 'shipped' => 'Expédiée', 'delivered' => 'Livrée', 'cancelled' => 'Annulée'];
        $this->notifications->create($customerId, 'order_status', "Commande #{$orderNumber} - Mise à jour", 'Le statut de votre commande a été mis à jour : ' . ($labels[$newStatus] ?? $newStatus), $orderId, 'order');
        if ($oldStatus === $newStatus) {
            return;
        }
        try {
            $customer = $this->db->queryOne('SELECT email, first_name FROM customers WHERE id = ?', [$customerId]);
            if ($customer !== null && isset($customer['email']) && $customer['email'] !== '') {
                $this->mail->sendOrderStatusUpdate($customer['email'], $customer['first_name'] ?? 'Client', $orderNumber, $newStatus, $orderId);
            }
        } catch (\Throwable $emailError) {
            error_log('[ADMIN ORDERS] Erreur envoi email: ' . $emailError->getMessage());
        }
    }
}
