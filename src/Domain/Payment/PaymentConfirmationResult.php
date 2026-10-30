<?php

declare(strict_types=1);

namespace App\Domain\Payment;

final class PaymentConfirmationResult
{
    public function __construct(
        public readonly ?array $order,
        public readonly string $paymentType,
        public readonly bool $alreadyProcessed,
        public readonly bool $amountMismatch = false,
    ) {}

    public function isPaid(): bool
    {
        return $this->order !== null && !$this->amountMismatch;
    }
}
