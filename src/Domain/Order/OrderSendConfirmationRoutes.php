<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Infrastructure\Mail\LegacyEmailGateway;

final class OrderSendConfirmationRoutes
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly Connection $db,
        private readonly LegacyEmailGateway $mail,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('orders/send-confirmation', [new CustomerGuard()])->post(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            $orderId = $data['order_id'] ?? null;
            if ($orderId === null) {
                throw new DomainException('order_id requis');
            }
            $order = $this->orders->findById((int) $orderId);
            if ($order === null || (int) $order['customer_id'] !== (int) $s->customerId()) {
                throw new NotFoundException('Commande non trouvée');
            }
            if (($order['confirmation_email_sent'] ?? false) === true) {
                return Response::json(['success' => true, 'message' => 'Email déjà envoyé']);
            }
            $customer = $this->db->queryOne('SELECT email, first_name, last_name FROM customers WHERE id = ?', [$s->customerId()]);
            if ($customer === null) {
                throw new NotFoundException('Client non trouvé');
            }
            [$subject, $html] = OrderConfirmationEmailBuilder::build(
                $order,
                $this->orders->itemsFor((int) $orderId),
                $this->orders->samplesFor((int) $orderId),
                $this->orders->catalogueItemsFor((int) $orderId),
                $this->orders->facadeItemsFor((int) $orderId),
            );
            if (!$this->mail->send($customer['email'], $subject, $html)) {
                throw new DomainException("Échec de l'envoi de l'email", 500);
            }
            $this->db->execute('UPDATE orders SET confirmation_email_sent = TRUE WHERE id = ?', [$orderId]);
            return Response::json(['success' => true, 'message' => 'Email de confirmation envoyé']);
        });
    }
}
