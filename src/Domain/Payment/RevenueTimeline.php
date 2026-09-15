<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use DateTime;

final class RevenueTimeline
{
    private const MONTH_LABELS = ['01' => 'Jan', '02' => 'Fév', '03' => 'Mar', '04' => 'Avr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Aoû', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Déc'];

    public static function days(string $dateFrom, array $revenueByDay): array
    {
        $timeline = [];
        $current = new DateTime($dateFrom);
        $end = new DateTime('now');
        while ($current <= $end) {
            $timeline[] = ['month' => $current->format('d/m'), 'revenue' => $revenueByDay[$current->format('Y-m-d')] ?? 0];
            $current->modify('+1 day');
        }
        return $timeline;
    }

    public static function months(int $monthsCount, array $revenueByMonth): array
    {
        $timeline = [];
        $current = new DateTime();
        $current->modify("-{$monthsCount} months");
        for ($i = 0; $i <= $monthsCount; $i++) {
            $monthNum = $current->format('m');
            $timeline[] = ['month' => self::MONTH_LABELS[$monthNum] ?? $monthNum, 'revenue' => $revenueByMonth[$current->format('Y-m')] ?? 0];
            $current->modify('+1 month');
        }
        return $timeline;
    }

    public static function monthsCount(string $period): int
    {
        return match ($period) {
            '1y' => 12,
            default => 3,
        };
    }
}
