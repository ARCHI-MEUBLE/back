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

final class OrderValidateRoutes
{
    public function __construct(private readonly Connection $db) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('orders/validate', [new CustomerGuard()])->post(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['order_id'])) {
                throw new DomainException('order_id requis');
            }
            $orderId = $data['order_id'];
            $paymentMethod = (string) ($data['payment_method'] ?? 'free_samples');
            $order = $this->db->queryOne('SELECT * FROM orders WHERE id = ? AND customer_id = ?', [$orderId, $s->customerId()]);
            if ($order === null) {
                throw new NotFoundException('Commande non trouvée');
            }
            $total = (float) ($order['total_amount'] ?? $order['total'] ?? 0);
            if ($total > 0) {
                throw new DomainException('Cette commande nécessite un paiement');
            }
            $this->db->execute(
                "UPDATE orders SET payment_status = 'paid', payment_method = ?, status = 'confirmed', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$paymentMethod, $orderId],
            );
            return Response::json(['success' => true, 'message' => 'Commande validée', 'order_id' => $orderId]);
        });
    }
}
