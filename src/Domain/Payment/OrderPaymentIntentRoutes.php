<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class OrderPaymentIntentRoutes
{
    public function __construct(private readonly Connection $db) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('orders/payment-intent', [new CustomerGuard()])->post(null, function (Request $r, Session $s): Response {
            $orderId = (int) ($r->queryString('id') ?? 0);
            if ($orderId === 0) {
                throw new DomainException('ID de commande manquant');
            }
            $data = $r->jsonOrEmpty();
            if (!isset($data['payment_intent_id'])) {
                throw new DomainException('Payment intent ID manquant');
            }
            $paymentType = $data['payment_type'] ?? 'full';
            $order = $this->db->queryOne('SELECT id, customer_id FROM orders WHERE id = ?', [$orderId]);
            if ($order === null || (int) $order['customer_id'] !== (int) $s->customerId()) {
                throw new ForbiddenException('Accès interdit');
            }
            $column = match ($paymentType) {
                'deposit' => 'deposit_stripe_intent_id',
                'balance' => 'balance_stripe_intent_id',
                default => 'stripe_payment_intent_id',
            };
            $statusColumn = match ($paymentType) {
                'deposit' => 'deposit_payment_status',
                'balance' => 'balance_payment_status',
                default => 'payment_status',
            };
            $this->db->execute(
                "UPDATE orders SET {$column} = ?, {$statusColumn} = 'pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$data['payment_intent_id'], $orderId],
            );
            return Response::json(['success' => true]);
        });
    }
}
