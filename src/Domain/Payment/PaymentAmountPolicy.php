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
        $due = self::cents(self::dueAmount($order, $paymentType));
        if ($due <= 0) {
            return null;
        }
        if (is_string($paymentLinkToken) && $paymentLinkToken !== '') {
            $link = $this->paymentLinks->getLinkByToken($paymentLinkToken);
            if ($link === null || (int) $link['order_id'] !== (int) $order['id'] || !self::linkMatchesDue($link, $order)) {
                return null;
            }
        }
        return $due;
    }

    public static function linkMatchesDue(array $link, array $order): bool
    {
        $type = (string) ($link['payment_type'] ?? 'full');
        $linkCents = (float) ($link['amount'] ?? 0) > 0 ? self::cents($link['amount']) : self::cents(self::dueAmount($order, $type));
        return $linkCents > 0 && $linkCents === self::cents(self::dueAmount($order, $type));
    }

    public function matches(array $order, string $paymentType, mixed $paymentLinkToken, int $amountReceived, string $currency): bool
    {
        $expected = $this->expectedCents($order, $paymentType, $paymentLinkToken);
        return $expected !== null && $expected === $amountReceived && strtolower($currency) === self::CURRENCY;
    }
}
