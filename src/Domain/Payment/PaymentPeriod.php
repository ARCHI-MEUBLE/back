<?php

declare(strict_types=1);

namespace App\Domain\Payment;

final class PaymentPeriod
{
    public static function days(string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '90d' => 90,
            '1y' => 365,
            default => 30,
        };
    }

    public static function since(string $period): string
    {
        $timestamp = strtotime('-' . self::days($period) . ' days');
        return date('Y-m-d 00:00:00', $timestamp === false ? time() : $timestamp);
    }

    public static function methodLabel(string $method): string
    {
        $map = ['card' => 'Carte bancaire', 'stripe' => 'Stripe', 'paypal' => 'PayPal', 'bank_transfer' => 'Virement bancaire', 'cash' => 'Espèces', 'check' => 'Chèque'];
        return $map[$method] ?? ucfirst($method);
    }
}
