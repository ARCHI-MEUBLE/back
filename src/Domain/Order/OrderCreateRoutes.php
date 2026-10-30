<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Shared\DomainException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class OrderCreateRoutes
{
    public function __construct(
        private readonly OrderCreationService $orders,
        private readonly AdminNotificationRepository $adminNotifications,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('orders/create', [new CustomerGuard()])->post(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['shipping_address']) || $data['shipping_address'] === '') {
                throw new DomainException('Adresse de livraison requise');
            }
            $result = $this->orders->createFromCart(
                (int) $s->customerId(),
                (string) $data['shipping_address'],
                (string) ($data['billing_address'] ?? $data['shipping_address']),
                (string) ($data['payment_method'] ?? 'stripe'),
                isset($data['notes']) ? (string) $data['notes'] : null,
            );
            $this->notifyAdmins($result);
            return Response::json(['success' => true, 'message' => 'Commande créée avec succès', 'order' => $result], 201);
        });
    }

    private function notifyAdmins(array $result): void
    {
        try {
            $samplesCount = $result['samples_count'] ?? 0;
            $customerName = isset($result['customer'])
                ? trim(($result['customer']['first_name'] ?? '') . ' ' . ($result['customer']['last_name'] ?? ''))
                : 'Client';
            $message = "Nouvelle commande #{$result['order_number']} de {$customerName} pour {$result['total']}€";
            if ($samplesCount > 0) {
                $message .= ' (+ ' . $samplesCount . ' échantillon' . ($samplesCount > 1 ? 's' : '') . ')';
            }
            $this->adminNotifications->notifyAllAdmins($samplesCount > 0 ? 'sample_order' : 'order', $message, (int) $result['id']);
        } catch (\Throwable $notificationFailure) {
            error_log('Erreur création notification: ' . $notificationFailure->getMessage());
        }
    }
}
