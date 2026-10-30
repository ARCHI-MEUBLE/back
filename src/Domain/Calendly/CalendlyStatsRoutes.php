<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

use App\Http\Guard\AdminGuard;
use App\Http\Response;
use App\Http\RouteCollection;

final class CalendlyStatsRoutes
{
    public function __construct(private readonly CalendlyStatsRepository $stats) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('calendly/appointments-stats', [new AdminGuard()])->get(null, fn(): Response => $this->show());
    }

    private function show(): Response
    {
        $counts = $this->stats->counts();
        $types = $this->stats->typeCounts();
        $cancellationRate = $counts['total'] > 0 ? round(($counts['cancelled'] / $counts['total']) * 100, 2) : 0;
        $thisMonth = $this->stats->thisMonthCount();
        $lastMonth = $this->stats->lastMonthCount();
        $trend = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 2) : 0;
        return Response::json([
            'success' => true,
            'stats' => [
                'total' => $counts['total'], 'scheduled' => $counts['scheduled'], 'completed' => $counts['completed'], 'cancelled' => $counts['cancelled'],
                'cancellation_rate' => $cancellationRate, 'phone_count' => $types['phone'], 'visio_count' => $types['visio'],
                'this_month_count' => $thisMonth, 'last_month_count' => $lastMonth, 'monthly_trend' => $trend,
            ],
            'charts' => [
                'weekly' => $this->stats->weekly(),
                'monthly' => $this->stats->monthly(),
                'status_distribution' => [
                    ['status' => 'scheduled', 'count' => $counts['scheduled'], 'label' => 'Prévus'],
                    ['status' => 'completed', 'count' => $counts['completed'], 'label' => 'Terminés'],
                    ['status' => 'cancelled', 'count' => $counts['cancelled'], 'label' => 'Annulés'],
                ],
                'type_distribution' => [
                    ['type' => 'phone', 'count' => $types['phone'], 'label' => 'Téléphone'],
                    ['type' => 'visio', 'count' => $types['visio'], 'label' => 'Visioconférence'],
                ],
            ],
        ]);
    }
}
