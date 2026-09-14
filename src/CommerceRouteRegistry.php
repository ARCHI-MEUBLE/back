<?php

declare(strict_types=1);

namespace App;

use App\Config\Settings;
use App\Db\Connection;
use App\Domain\Facade\FacadeDrillingTypeRepository;
use App\Domain\Facade\FacadeDrillingTypeRoutes;
use App\Domain\Facade\FacadeDxfGenerator;
use App\Domain\Facade\FacadeDxfRoutes;
use App\Domain\Facade\FacadeMaterialRepository;
use App\Domain\Facade\FacadeMaterialRoutes;
use App\Domain\Facade\FacadeRepository;
use App\Domain\Facade\FacadeRoutes;
use App\Domain\Facade\FacadeSettingRepository;
use App\Domain\Facade\FacadeSettingRoutes;
use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\QuoteRequest\QuoteRequestRepository;
use App\Domain\QuoteRequest\QuoteRequestRoutes;
use App\Domain\QuoteRequest\QuoteRequestService;
use App\Http\RouteCollection;

final class CommerceRouteRegistry
{
    public static function register(RouteCollection $routes, Settings $settings, Connection $db): void
    {
        (new FacadeMaterialRoutes(new FacadeMaterialRepository($db)))->register($routes);
        (new FacadeDrillingTypeRoutes(new FacadeDrillingTypeRepository($db)))->register($routes);
        (new FacadeRoutes(new FacadeRepository($db)))->register($routes);
        (new FacadeSettingRoutes(new FacadeSettingRepository($db)))->register($routes);
        (new FacadeDxfRoutes($db, new FacadeDxfGenerator($settings->rootDir . '/templates/dxf')))->register($routes);
        (new QuoteRequestRoutes(new QuoteRequestService(
            new QuoteRequestRepository($db),
            new AdminNotificationRepository($db),
            $settings->rootDir . '/backend/uploads/quote-requests',
        )))->register($routes);
    }
}
