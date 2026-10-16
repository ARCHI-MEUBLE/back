<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Notification\AdminNotificationRepository;
use App\Infrastructure\Stripe\StripeObjectReader;

final class PaymentRefundHandler
{
    private const COLUMNS = [
        'stripe_payment_intent_id' => 'payment_status',
        'deposit_stripe_intent_id' => 'deposit_payment_status',
        'balance_stripe_intent_id' => 'balance_payment_status',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly AdminNotificationRepository $adminNotifications,
    ) {}

    public function handle(object $charge): void
    {
        $intentId = (string) StripeObjectReader::string($charge, 'payment_intent');
        if ($intentId === '') {
            return;
        }
        foreach (self::COLUMNS as $intentColumn => $statusColumn) {
            $order = $this->db->queryOne("SELECT id FROM orders WHERE {$intentColumn} = ?", [$intentId]);
            if ($order !== null) {
                $this->apply((int) $order['id'], $statusColumn, StripeObjectReader::get($charge, 'refunded') === true, StripeObjectReader::int($charge, 'amount_refunded'));
                return;
            }
        }
    }

    private function apply(int $orderId, string $statusColumn, bool $fullyRefunded, int $amountRefunded): void
    {
        if (!$fullyRefunded) {
            $this->db->execute("UPDATE orders SET payment_status = 'partially_refunded', updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$orderId]);
            $this->adminNotifications->notifyAllAdmins('payment', sprintf('Remboursement partiel de %.2f€ sur la commande #%d', $amountRefunded / 100, $orderId), $orderId);
            return;
        }
        $cancel = $statusColumn !== 'balance_payment_status';
        $this->db->execute(
            "UPDATE orders SET {$statusColumn} = 'refunded', payment_status = 'refunded'" . ($cancel ? ", status = 'cancelled'" : '') . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$orderId],
        );
        $this->adminNotifications->notifyAllAdmins('payment', sprintf('Remboursement de %.2f€ sur la commande #%d', $amountRefunded / 100, $orderId), $orderId);
    }
}
