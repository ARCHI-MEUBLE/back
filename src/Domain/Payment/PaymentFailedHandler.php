<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Order\OrderRepository;
use App\Infrastructure\Mail\LegacyEmailGateway;
use App\Infrastructure\Stripe\StripeObjectReader;

final class PaymentFailedHandler
{
    public function __construct(
        private readonly Connection $db,
        private readonly OrderRepository $orders,
        private readonly CustomerRepository $customers,
        private readonly AdminNotificationRepository $adminNotifications,
        private readonly LegacyEmailGateway $mail,
    ) {}

    public function handle(object $paymentIntent): void
    {
        $intentId = (string) StripeObjectReader::string($paymentIntent, 'id');
        $metadata = StripeObjectReader::metadataArray($paymentIntent);
        $paymentType = $metadata['payment_type'] ?? 'full';
        $orderId = $metadata['order_id'] ?? null;
        $order = $this->findOrder($intentId, $orderId, $paymentType);
        if ($order === null) {
            return;
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
            "UPDATE orders SET {$statusColumn} = 'failed', {$column} = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$intentId, $order['id']],
        );
        $fullOrder = (array) $this->orders->findById((int) $order['id']);
        $customer = (array) $this->customers->findRawById((int) $order['customer_id']);
        $this->mail->sendPaymentFailed($fullOrder, $customer);
        $this->adminNotifications->notifyAllAdmins('payment', "Échec du paiement ({$paymentType}) pour la commande #{$order['id']}", (int) $order['id']);
    }

    private function findOrder(string $intentId, ?string $orderId, string $paymentType): ?array
    {
        if ($orderId !== null) {
            return $this->db->queryOne('SELECT id, customer_id FROM orders WHERE id = ?', [$orderId]);
        }
        $column = match ($paymentType) {
            'deposit' => 'deposit_stripe_intent_id',
            'balance' => 'balance_stripe_intent_id',
            default => 'stripe_payment_intent_id',
        };
        return $this->db->queryOne("SELECT id, customer_id FROM orders WHERE {$column} = ?", [$intentId]);
    }
}
