<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class RecentTransactionsRoutes
{
    public function __construct(private readonly Connection $db) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/recent-transactions', [new AdminGuard()])->get(null, fn(Request $r): Response => $this->get($r));
    }

    private function get(Request $r): Response
    {
        $dateFrom = PaymentPeriod::since($r->queryString('period') ?? '30d');
        $limit = (int) ($r->queryString('limit') ?? 20);
        $rows = $this->db->query(
            "SELECT o.id, o.order_number, o.total_amount as amount, o.payment_method, o.payment_status, o.stripe_payment_intent_id, o.created_at,
                    c.first_name || ' ' || c.last_name as customer_name, c.email as customer_email
             FROM orders o JOIN customers c ON o.customer_id = c.id
             WHERE o.created_at >= ? ORDER BY o.created_at DESC LIMIT ?",
            [$dateFrom, $limit],
        );
        return Response::json(array_map(static fn(array $row): array => [
            'id' => (string) $row['id'],
            'order_number' => $row['order_number'],
            'customer_name' => $row['customer_name'],
            'customer_email' => $row['customer_email'],
            'amount' => (float) $row['amount'],
            'payment_method' => PaymentPeriod::methodLabel($row['payment_method']),
            'payment_status' => $row['payment_status'],
            'stripe_payment_intent_id' => $row['stripe_payment_intent_id'] ?? null,
            'created_at' => $row['created_at'],
        ], $rows));
    }
}
