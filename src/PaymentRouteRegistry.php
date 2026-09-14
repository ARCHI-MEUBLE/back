<?php

declare(strict_types=1);

namespace App;

use App\Config\Settings;
use App\Db\Connection;
use App\Domain\Payment\ExportPaymentsRoutes;
use App\Domain\Payment\PaymentAnalyticsRepository;
use App\Domain\Payment\PaymentAnalyticsRoutes;
use App\Domain\Payment\PaymentStrategyRepository;
use App\Domain\Payment\PaymentStrategyRoutes;
use App\Domain\Payment\RecentTransactionsRoutes;
use App\Domain\Payment\SyncPaymentStatusRoutes;
use App\Http\RouteCollection;
use App\Infrastructure\Stripe\StripeGateway;

final class PaymentRouteRegistry
{
    public static function register(RouteCollection $routes, Settings $settings, Connection $db): void
    {
        $stripe = new StripeGateway($settings->stripe);
        (new PaymentStrategyRoutes(new PaymentStrategyRepository($db)))->register($routes);
        (new PaymentAnalyticsRoutes(new PaymentAnalyticsRepository($db)))->register($routes);
        (new RecentTransactionsRoutes($db))->register($routes);
        (new ExportPaymentsRoutes($db))->register($routes);
        (new SyncPaymentStatusRoutes($db, $stripe))->register($routes);
    }
}
