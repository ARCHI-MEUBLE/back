<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Domain\Shared\NotFoundException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class OrderPaymentConfirmedRoutes
{
    public function __construct(
        private readonly Connection $db,
        private readonly AdminNotificationRepository $adminNotifications,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('orders/payment-confirmed', [new CustomerGuard()])->post(null, function (Request $r, Session $s): Response {
            $orderId = (int) ($r->queryString('id') ?? 0);
            if ($orderId === 0) {
                throw new DomainException('ID de commande manquant');
            }
            $data = $r->jsonOrEmpty();
            if (!isset($data['payment_intent_id']) || !isset($data['payment_status'])) {
                throw new DomainException('Données manquantes');
            }
            $order = $this->db->queryOne('SELECT id, customer_id FROM orders WHERE id = ?', [$orderId]);
            if ($order === null) {
                throw new NotFoundException('Commande non trouvée');
            }
            if ((int) $order['customer_id'] !== (int) $s->customerId()) {
                throw new ForbiddenException('Accès interdit');
            }
            $this->db->execute(
                "UPDATE orders SET stripe_payment_intent_id = ?, payment_status = ?, status = 'confirmed', confirmed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$data['payment_intent_id'], $data['payment_status'], $orderId],
            );
            $this->adminNotifications->notifyAllAdmins('payment', "Paiement confirmé pour la commande #{$orderId}", $orderId);
            return Response::json(['success' => true, 'message' => 'Paiement confirmé']);
        });
    }
}
