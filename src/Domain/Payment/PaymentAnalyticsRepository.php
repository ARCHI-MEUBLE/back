<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;

final class PaymentAnalyticsRepository
{
    public function __construct(private readonly Connection $db) {}

    public function kpis(string $dateFrom): array
    {
        return (array) $this->db->queryOne(
            "SELECT
                SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_revenue,
                COUNT(*) as total_orders,
                COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payments,
                COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as successful_payments,
                COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed_payments
             FROM orders WHERE created_at >= ?",
            [$dateFrom],
        );
    }

    public function revenueByDay(string $dateFrom): array
    {
        $rows = $this->db->query(
            "SELECT TO_CHAR(created_at, 'YYYY-MM-DD') as period, SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as revenue
             FROM orders WHERE created_at >= ? GROUP BY period ORDER BY period ASC",
            [$dateFrom],
        );
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['period']] = (float) $row['revenue'];
        }
        return $byDay;
    }

    public function revenueByMonth(int $monthsCount): array
    {
        $rows = $this->db->query(
            "SELECT TO_CHAR(created_at, 'YYYY-MM') as month, SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as revenue
             FROM orders WHERE created_at >= NOW() - INTERVAL '{$monthsCount} months' GROUP BY month ORDER BY month ASC",
        );
        $byMonth = [];
        foreach ($rows as $row) {
            $byMonth[$row['month']] = (float) $row['revenue'];
        }
        return $byMonth;
    }

    public function paymentMethods(string $dateFrom): array
    {
        return $this->db->query(
            'SELECT payment_method as method, COUNT(*) as count, (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders WHERE created_at >= ?)) as percentage
             FROM orders WHERE created_at >= ? GROUP BY payment_method ORDER BY count DESC',
            [$dateFrom, $dateFrom],
        );
    }
}
