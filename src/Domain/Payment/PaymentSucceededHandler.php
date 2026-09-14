<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Cart\CartRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Order\OrderRepository;
use App\Infrastructure\Installment\LegacyInstallmentGateway;
use App\Infrastructure\Invoice\LegacyInvoiceGateway;
use App\Infrastructure\Mail\LegacyEmailGateway;
use App\Infrastructure\PaymentLink\LegacyPaymentLinkGateway;
use App\Infrastructure\Stripe\StripeObjectReader;
use Throwable;

final class PaymentSucceededHandler
{
    public function __construct(
        private readonly Connection $db,
        private readonly OrderRepository $orders,
        private readonly CustomerRepository $customers,
        private readonly CartRepository $cart,
        private readonly AdminNotificationRepository $adminNotifications,
        private readonly LegacyEmailGateway $mail,
        private readonly LegacyInvoiceGateway $invoices,
        private readonly LegacyInstallmentGateway $installments,
        private readonly LegacyPaymentLinkGateway $paymentLinks,
    ) {}

    public function handle(object $paymentIntent): void
    {
        $intentId = (string) StripeObjectReader::string($paymentIntent, 'id');
        $metadata = StripeObjectReader::metadataArray($paymentIntent);
        $paymentType = $metadata['payment_type'] ?? 'full';
        $paymentLinkToken = $metadata['payment_link_token'] ?? null;
        $orderId = $metadata['order_id'] ?? null;
        [$order, $paymentType] = $this->findOrder($intentId, $orderId, $paymentType);
        if ($order === null) {
            return;
        }
        if ($paymentType === 'full') {
            if ($intentId === ($order['deposit_stripe_intent_id'] ?? '')) {
                $paymentType = 'deposit';
            } elseif ($intentId === ($order['balance_stripe_intent_id'] ?? '')) {
                $paymentType = 'balance';
            }
        }
        if ($this->alreadyProcessed($order, $paymentType)) {
            return;
        }
        $this->applyPaymentStatus($order, $paymentType, $intentId);
        if ($paymentType === 'full' || $paymentType === 'deposit') {
            $this->cart->clear((int) $order['customer_id']);
        }
        $customer = (array) $this->customers->findRawById((int) $order['customer_id']);
        $items = $this->orders->itemsFor((int) $order['id']);
        $fullOrder = (array) $this->orders->findById((int) $order['id']);
        $this->mail->sendOrderConfirmation($fullOrder, $customer, $items, $paymentType);
        $this->mail->sendNewOrderNotificationToAdmin($fullOrder, $customer, $items);
        if ($paymentLinkToken !== null) {
            $this->paymentLinks->markAsUsed((string) $paymentLinkToken);
        }
        try {
            $this->invoices->generateInvoice($fullOrder, $customer, $items);
        } catch (Throwable $e) {
            error_log("Failed to generate invoice for order ID: {$order['id']}: " . $e->getMessage());
        }
        $installmentsCount = $metadata['installments'] ?? 1;
        if ((int) $installmentsCount === 3) {
            try {
                $this->installments->createInstallments((int) $order['id'], (int) $order['customer_id'], (float) $fullOrder['total_amount']);
            } catch (Throwable $e) {
                error_log("Failed to create installments for order ID: {$order['id']}: " . $e->getMessage());
            }
        }
        $label = match ($paymentType) {
            'deposit' => "Acompte payé pour la commande #{$order['id']}",
            'balance' => "Solde payé pour la commande #{$order['id']}",
            default => "Paiement confirmé pour la commande #{$order['id']}",
        };
        $this->adminNotifications->notifyAllAdmins('payment', $label, (int) $order['id']);
    }

    private function findOrder(string $intentId, ?string $orderId, string $paymentType): array
    {
        if ($orderId !== null) {
            $order = $this->orders->findById((int) $orderId);
            if ($order !== null) {
                return [$order, $paymentType];
            }
        }
        $order = $this->db->queryOne('SELECT * FROM orders WHERE deposit_stripe_intent_id = ?', [$intentId]);
        if ($order !== null) {
            return [$order, 'deposit'];
        }
        $order = $this->db->queryOne('SELECT * FROM orders WHERE balance_stripe_intent_id = ?', [$intentId]);
        if ($order !== null) {
            return [$order, 'balance'];
        }
        $order = $this->db->queryOne('SELECT * FROM orders WHERE stripe_payment_intent_id = ?', [$intentId]);
        return $order !== null ? [$order, 'full'] : [null, $paymentType];
    }

    private function alreadyProcessed(array $order, string $paymentType): bool
    {
        return match ($paymentType) {
            'deposit' => ($order['deposit_payment_status'] ?? 'pending') === 'paid',
            'balance' => ($order['balance_payment_status'] ?? 'pending') === 'paid',
            default => $order['payment_status'] === 'paid',
        };
    }

    private function applyPaymentStatus(array $order, string $paymentType, string $intentId): void
    {
        match ($paymentType) {
            'deposit' => $this->db->execute(
                "UPDATE orders SET deposit_payment_status = 'paid', payment_status = 'partially_paid', status = 'confirmed', deposit_stripe_intent_id = ?, confirmed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$intentId, $order['id']],
            ),
            'balance' => $this->db->execute(
                "UPDATE orders SET balance_payment_status = 'paid', payment_status = 'paid', balance_stripe_intent_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$intentId, $order['id']],
            ),
            default => $this->db->execute(
                "UPDATE orders SET payment_status = 'paid', status = 'confirmed', stripe_payment_intent_id = ?, confirmed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$intentId, $order['id']],
            ),
        };
    }
}
