<?php

declare(strict_types=1);

namespace App\Domain\Payment;

final class PaymentAmountPolicy
{
    public const CURRENCY = 'eur';

    public function __construct(private readonly PaymentLinkRepository $paymentLinks) {}

    public static function cents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function dueAmount(array $order, string $paymentType): float
    {
        return (float) match ($paymentType) {
            'deposit' => $order['deposit_amount'] ?? 0,
            'balance' => $order['remaining_amount'] ?? 0,
            default => $order['total_amount'] ?? 0,
        };
    }

    public function expectedCents(array $order, string $paymentType, mixed $paymentLinkToken): ?int
    {
        if (is_string($paymentLinkToken) && $paymentLinkToken !== '') {
            $link = $this->paymentLinks->getLinkByToken($paymentLinkToken);
            if ($link === null || (int) $link['order_id'] !== (int) $order['id']) {
                return null;
            }
            $amount = (float) ($link['amount'] ?? 0) > 0 ? (float) $link['amount'] : self::dueAmount($order, $paymentType);
        } else {
            $amount = self::dueAmount($order, $paymentType);
        }
        return $amount > 0 ? self::cents($amount) : null;
    }

    public function matches(array $order, string $paymentType, mixed $paymentLinkToken, int $amountReceived, string $currency): bool
    {
        $expected = $this->expectedCents($order, $paymentType, $paymentLinkToken);
        return $expected !== null && $expected === $amountReceived && strtolower($currency) === self::CURRENCY;
    }
}
