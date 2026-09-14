<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Infrastructure\Installment\LegacyInstallmentGateway;
use App\Infrastructure\Stripe\StripeObjectReader;
use Throwable;

final class PaymentSucceededHandler
{
    public function __construct(
        private readonly PaymentConfirmationService $confirmation,
        private readonly LegacyInstallmentGateway $installments,
    ) {}

    public function handle(object $paymentIntent): void
    {
        $intentId = (string) StripeObjectReader::string($paymentIntent, 'id');
        $metadata = StripeObjectReader::metadataArray($paymentIntent);
        $result = $this->confirmation->confirm($intentId, $metadata);
        if ($result->order === null || $result->alreadyProcessed) {
            return;
        }
        $installmentsCount = $metadata['installments'] ?? 1;
        if ((int) $installmentsCount === 3) {
            try {
                $this->installments->createInstallments((int) $result->order['id'], (int) $result->order['customer_id'], (float) $result->order['total_amount']);
            } catch (Throwable $e) {
                error_log("Failed to create installments for order ID: {$result->order['id']}: " . $e->getMessage());
            }
        }
    }
}
