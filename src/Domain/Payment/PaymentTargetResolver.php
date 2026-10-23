<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;

final class PaymentTargetResolver
{
    public const COLUMNS = [
        'full' => ['stripe_payment_intent_id', 'payment_status'],
        'deposit' => ['deposit_stripe_intent_id', 'deposit_payment_status'],
        'balance' => ['balance_stripe_intent_id', 'balance_payment_status'],
    ];

    public function __construct(private readonly Connection $db) {}

    public static function validType(mixed $type): ?string
    {
        return is_string($type) && isset(self::COLUMNS[$type]) ? $type : null;
    }

    public function typeFor(array $order, ?string $requestedType, ?int $amountCents): ?string
    {
        if (in_array($order['payment_status'] ?? null, ['paid', 'refunded'], true) || ($order['status'] ?? null) === 'cancelled') {
            return null;
        }
        $types = $requestedType !== null ? [$requestedType] : (($order['payment_strategy'] ?? 'full') === 'deposit' ? ['deposit', 'balance', 'full'] : ['full', 'deposit', 'balance']);
        foreach ($types as $type) {
            if (($order[self::COLUMNS[$type][1]] ?? null) === 'paid' || ($type === 'balance' && ($order['deposit_payment_status'] ?? null) !== 'paid')) {
                continue;
            }
            $due = PaymentAmountPolicy::cents(PaymentAmountPolicy::dueAmount($order, $type));
            if ($due > 0 && ($amountCents === null || $amountCents === $due)) {
                return $type;
            }
        }
        return null;
    }

    public function findByAmount(int $customerId, int $amountCents): ?array
    {
        $orders = $this->db->query(
            "SELECT * FROM orders WHERE customer_id = ? AND COALESCE(payment_status, 'pending') NOT IN ('paid', 'refunded') AND COALESCE(status, '') <> 'cancelled'
             ORDER BY created_at DESC, id DESC LIMIT 20",
            [$customerId],
        );
        foreach ($orders as $order) {
            $type = $this->typeFor($order, null, $amountCents);
            if ($type !== null) {
                return [$order, $type];
            }
        }
        return null;
    }

    public function bind(int $orderId, string $type, string $intentId): void
    {
        [$column, $statusColumn] = self::COLUMNS[$type];
        $this->db->execute("UPDATE orders SET {$column} = ?, {$statusColumn} = 'pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$intentId, $orderId]);
    }
}
