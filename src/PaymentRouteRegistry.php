<?php

declare(strict_types=1);

namespace App;

use App\Config\Settings;
use App\Db\Connection;
use App\Domain\Cart\CartRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Order\OrderRepository;
use App\Domain\Payment\ExportPaymentsRoutes;
use App\Domain\Payment\OrderPaymentConfirmedRoutes;
use App\Domain\Payment\OrderPaymentIntentRoutes;
use App\Domain\Payment\PaymentAnalyticsRepository;
use App\Domain\Payment\PaymentAnalyticsRoutes;
use App\Domain\Payment\PaymentFailedHandler;
use App\Domain\Payment\PaymentStrategyRepository;
use App\Domain\Payment\PaymentStrategyRoutes;
use App\Domain\Payment\PaymentSucceededHandler;
use App\Domain\Payment\RecentTransactionsRoutes;
use App\Domain\Payment\StripeCreatePaymentIntentRoutes;
use App\Domain\Payment\StripeWebhookRoutes;
use App\Domain\Payment\SyncPaymentStatusRoutes;
use App\Http\RouteCollection;
use App\Infrastructure\Installment\LegacyInstallmentGateway;
use App\Infrastructure\Invoice\LegacyInvoiceGateway;
use App\Infrastructure\Mail\LegacyEmailGateway;
use App\Infrastructure\PaymentLink\LegacyPaymentLinkGateway;
use App\Infrastructure\Stripe\StripeGateway;
use App\Lib\Logger;

final class PaymentRouteRegistry
{
    public static function register(RouteCollection $routes, Settings $settings, Connection $db, Logger $logger): void
    {
        $stripe = new StripeGateway($settings->stripe);
        $customers = new CustomerRepository($db);
        $orders = new OrderRepository($db);
        $adminNotifications = new AdminNotificationRepository($db);
        $mail = new LegacyEmailGateway($settings->rootDir);
        (new PaymentStrategyRoutes(new PaymentStrategyRepository($db)))->register($routes);
        (new PaymentAnalyticsRoutes(new PaymentAnalyticsRepository($db)))->register($routes);
        (new RecentTransactionsRoutes($db))->register($routes);
        (new ExportPaymentsRoutes($db))->register($routes);
        (new SyncPaymentStatusRoutes($db, $stripe))->register($routes);
        (new OrderPaymentIntentRoutes($db))->register($routes);
        (new OrderPaymentConfirmedRoutes($db, $adminNotifications))->register($routes);
        (new StripeCreatePaymentIntentRoutes($stripe, $customers))->register($routes);
        $succeeded = new PaymentSucceededHandler(
            $db,
            $orders,
            $customers,
            new CartRepository($db),
            $adminNotifications,
            $mail,
            new LegacyInvoiceGateway($settings->rootDir),
            new LegacyInstallmentGateway($settings->rootDir),
            new LegacyPaymentLinkGateway($settings->rootDir),
        );
        $failed = new PaymentFailedHandler($db, $orders, $customers, $adminNotifications, $mail);
        (new StripeWebhookRoutes($stripe, $db, $logger, $succeeded, $failed))->register($routes);
    }
}
