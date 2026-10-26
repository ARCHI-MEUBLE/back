<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Cart\CartRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Order\OrderRepository;
use App\Infrastructure\Invoice\LegacyInvoiceGateway;
use App\Infrastructure\Mail\EmailGateway;
use Throwable;

final class PaymentConfirmationService
{
    public function __construct(
        private readonly Connection $db,
        private readonly OrderRepository $orders,
        private readonly CustomerRepository $customers,
        private readonly CartRepository $cart,
        private readonly AdminNotificationRepository $adminNotifications,
        private readonly EmailGateway $mail,
        private readonly LegacyInvoiceGateway $invoices,
        private readonly PaymentLinkRepository $paymentLinks,
        private readonly PaymentAmountPolicy $amounts,
    ) {}

    public function confirm(string $intentId, array $metadata, int $amountReceived, string $currency): PaymentConfirmationResult
    {
        $paymentType = (string) ($metadata['payment_type'] ?? 'full');
        $paymentLinkToken = $metadata['payment_link_token'] ?? null;
        $orderId = $metadata['order_id'] ?? null;
        [$order, $paymentType] = $this->findOrder($intentId, $orderId, $paymentType);
        if ($order === null) {
            return new PaymentConfirmationResult(null, $paymentType, false);
        }
        if ($paymentType === 'full') {
            if ($intentId === ($order['deposit_stripe_intent_id'] ?? '')) {
                $paymentType = 'deposit';
            } elseif ($intentId === ($order['balance_stripe_intent_id'] ?? '')) {
                $paymentType = 'balance';
            }
        }
        if (!$this->amounts->matches($order, $paymentType, $paymentLinkToken, $amountReceived, $currency)) {
            $this->reportMismatch($order, $paymentType, $intentId, $amountReceived, $currency);
            return new PaymentConfirmationResult($order, $paymentType, false, amountMismatch: true);
        }
        if ($this->applyPaymentStatus($order, $paymentType, $intentId) === 0) {
            return new PaymentConfirmationResult($order, $paymentType, true);
        }
        $this->afterPayment($order, $paymentType, $paymentLinkToken);
        return new PaymentConfirmationResult($order, $paymentType, false);
    }

    private function afterPayment(array $order, string $paymentType, mixed $paymentLinkToken): void
    {
        if ($paymentType === 'full' || $paymentType === 'deposit') {
            $this->cart->clear((int) $order['customer_id']);
        }
        $customer = (array) $this->customers->findRawById((int) $order['customer_id']);
        $items = $this->orders->itemsFor((int) $order['id']);
        $fullOrder = (array) $this->orders->findById((int) $order['id']);
        $this->mail->sendOrderConfirmation($fullOrder, $customer, $items, $paymentType);
        $this->mail->sendNewOrderNotificationToAdmin($fullOrder, $customer, $items);
        if (is_string($paymentLinkToken) && $paymentLinkToken !== '') {
            $this->paymentLinks->markAsUsed($paymentLinkToken);
        }
        try {
            $this->invoices->generateInvoice($fullOrder, $customer, $items);
        } catch (Throwable $e) {
            error_log("Failed to generate invoice for order ID: {$order['id']}: " . $e->getMessage());
        }
        $label = match ($paymentType) {
            'deposit' => "Acompte payé pour la commande #{$order['id']}",
            'balance' => "Solde payé pour la commande #{$order['id']}",
            default => "Paiement confirmé pour la commande #{$order['id']}",
        };
        $this->adminNotifications->notifyAllAdmins('payment', $label, (int) $order['id']);
    }

    private function reportMismatch(array $order, string $paymentType, string $intentId, int $amountReceived, string $currency): void
    {
        $message = sprintf(
            'Paiement %s (%s) NON appliqué à la commande #%d : %.2f %s reçus ne correspondent pas au montant attendu',
            $intentId,
            $paymentType,
            (int) $order['id'],
            $amountReceived / 100,
            strtoupper($currency),
        );
        error_log($message);
        $this->adminNotifications->notifyAllAdmins('payment', $message, (int) $order['id']);
    }

    private function findOrder(string $intentId, mixed $orderId, string $paymentType): array
    {
        if (is_scalar($orderId) && (int) $orderId > 0) {
            $order = $this->orders->findById((int) $orderId);
            if ($order !== null) {
                return [$order, $paymentType];
            }
        }
        foreach (['deposit_stripe_intent_id' => 'deposit', 'balance_stripe_intent_id' => 'balance', 'stripe_payment_intent_id' => 'full'] as $column => $type) {
            $order = $this->db->queryOne("SELECT * FROM orders WHERE {$column} = ?", [$intentId]);
            if ($order !== null) {
                return [$order, $type];
            }
        }
        return [null, $paymentType];
    }

    private function applyPaymentStatus(array $order, string $paymentType, string $intentId): int
    {
        return match ($paymentType) {
            'deposit' => $this->db->execute(
                "UPDATE orders SET deposit_payment_status = 'paid', payment_status = 'partially_paid', status = 'confirmed', deposit_stripe_intent_id = ?, confirmed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND deposit_payment_status IS DISTINCT FROM 'paid'",
                [$intentId, $order['id']],
            ),
            'balance' => $this->db->execute(
                "UPDATE orders SET balance_payment_status = 'paid', payment_status = 'paid', balance_stripe_intent_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND balance_payment_status IS DISTINCT FROM 'paid'",
                [$intentId, $order['id']],
            ),
            default => $this->db->execute(
                "UPDATE orders SET payment_status = 'paid', status = 'confirmed', stripe_payment_intent_id = ?, confirmed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND payment_status IS DISTINCT FROM 'paid'",
                [$intentId, $order['id']],
            ),
        };
    }
}
