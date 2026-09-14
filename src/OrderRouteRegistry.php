<?php

declare(strict_types=1);

namespace App;

use App\Config\Settings;
use App\Db\Connection;
use App\Domain\Cart\CartRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Notification\NotificationRepository;
use App\Domain\Order\AdminOrderFromConfigRoutes;
use App\Domain\Order\AdminOrderRoutes;
use App\Domain\Order\OrderCreateRoutes;
use App\Domain\Order\OrderCreationRepository;
use App\Domain\Order\OrderCreationService;
use App\Domain\Order\OrderDeleteRoutes;
use App\Domain\Order\OrderListRoutes;
use App\Domain\Order\OrderRepository;
use App\Domain\Order\OrderSendConfirmationRoutes;
use App\Domain\Order\OrderValidateRoutes;
use App\Http\RouteCollection;
use App\Infrastructure\Mail\LegacyEmailGateway;

final class OrderRouteRegistry
{
    public static function register(RouteCollection $routes, Settings $settings, Connection $db): void
    {
        $orders = new OrderRepository($db);
        $customers = new CustomerRepository($db);
        $mail = new LegacyEmailGateway($settings->rootDir);
        $creation = new OrderCreationService($db, new OrderCreationRepository($db, new CartRepository($db)), $customers);
        (new OrderCreateRoutes($creation, new AdminNotificationRepository($db)))->register($routes);
        (new OrderListRoutes($orders))->register($routes);
        (new OrderDeleteRoutes($orders))->register($routes);
        (new OrderValidateRoutes($db))->register($routes);
        (new OrderSendConfirmationRoutes($orders, $db, $mail))->register($routes);
        (new AdminOrderRoutes($orders, $customers, $db, new NotificationRepository($db), $mail))->register($routes);
        (new AdminOrderFromConfigRoutes($db, new AdminNotificationRepository($db)))->register($routes);
    }
}
