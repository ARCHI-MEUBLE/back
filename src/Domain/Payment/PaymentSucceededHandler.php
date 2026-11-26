<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Infrastructure\Stripe\StripeObjectReader;

final class PaymentSucceededHandler
{
    public function __construct(private readonly PaymentConfirmationService $confirmation) {}

    public function handle(object $paymentIntent): void
    {
        $this->confirmation->confirm(
            (string) StripeObjectReader::string($paymentIntent, 'id'),
            StripeObjectReader::metadataArray($paymentIntent),
            StripeObjectReader::amountReceived($paymentIntent),
            (string) StripeObjectReader::string($paymentIntent, 'currency'),
        );
    }
}
