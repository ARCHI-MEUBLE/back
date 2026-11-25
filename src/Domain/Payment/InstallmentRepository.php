<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;

final class InstallmentRepository
{
    public function __construct(private readonly Connection $db) {}

    public function pending(): array
    {
        return $this->db->query(
            "SELECT pi.*, o.order_number, o.stripe_payment_intent_id as order_payment_intent, c.first_name, c.last_name, c.email, c.stripe_customer_id
             FROM payment_installments pi
             JOIN orders o ON pi.order_id = o.id
             JOIN customers c ON pi.customer_id = c.id
             WHERE pi.status = 'pending' AND pi.due_date <= DATE('now') AND c.stripe_customer_id IS NOT NULL
             ORDER BY pi.due_date ASC",
        );
    }

    public function markPaid(int $id, string $stripePaymentIntentId): void
    {
        $this->db->execute(
            "UPDATE payment_installments SET status = 'paid', stripe_payment_intent_id = ?, paid_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$stripePaymentIntentId, $id],
        );
    }

    public function markFailed(int $id): void
    {
        $this->db->execute("UPDATE payment_installments SET status = 'failed', updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$id]);
    }
}
