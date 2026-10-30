<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class PaymentAnalyticsRoutes
{
    public function __construct(private readonly PaymentAnalyticsRepository $analytics) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/payment-analytics', [new AdminGuard()])->get(null, fn(Request $r): Response => $this->get($r));
    }

    private function get(Request $r): Response
    {
        $period = $r->queryString('period') ?? '30d';
        $dateFrom = PaymentPeriod::since($period);
        $kpis = $this->analytics->kpis($dateFrom);
        $successful = (int) ($kpis['successful_payments'] ?? 0);
        $totalRevenue = (float) ($kpis['total_revenue'] ?? 0);
        $revenueByMonth = in_array($period, ['7d', '30d'], true)
            ? RevenueTimeline::days($dateFrom, $this->analytics->revenueByDay($dateFrom))
            : RevenueTimeline::months(RevenueTimeline::monthsCount($period), $this->analytics->revenueByMonth(RevenueTimeline::monthsCount($period)));
        $paymentMethods = array_map(static fn(array $row): array => [
            'method' => PaymentPeriod::methodLabel($row['method']),
            'count' => (int) $row['count'],
            'percentage' => (float) $row['percentage'],
        ], $this->analytics->paymentMethods($dateFrom));
        return Response::json([
            'total_revenue' => $totalRevenue,
            'total_orders' => (int) ($kpis['total_orders'] ?? 0),
            'pending_payments' => (int) ($kpis['pending_payments'] ?? 0),
            'successful_payments' => $successful,
            'failed_payments' => (int) ($kpis['failed_payments'] ?? 0),
            'average_order_value' => $successful > 0 ? $totalRevenue / $successful : 0,
            'revenue_by_month' => $revenueByMonth,
            'payment_methods_distribution' => $paymentMethods,
        ]);
    }
}
