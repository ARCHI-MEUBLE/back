<?php

declare(strict_types=1);

namespace App;

use App\Config\Env;
use App\Config\Settings;
use App\Db\Connection;
use App\Domain\Admin\AdminRepository;
use App\Domain\Catalogue\AdminCatalogueRoutes;
use App\Domain\Catalogue\AdminCatalogueVariationRoutes;
use App\Domain\Catalogue\CatalogueRepository;
use App\Domain\Catalogue\CatalogueRoutes;
use App\Domain\Catalogue\CatalogueVariationRepository;
use App\Domain\Config\PublicConfigRoutes;
use App\Domain\EmailTemplate\EmailTemplateRepository;
use App\Domain\EmailTemplate\EmailTemplateRoutes;
use App\Domain\Notification\AdminNotificationRepository;
use App\Domain\Notification\AdminNotificationRoutes;
use App\Domain\Notification\NotificationRepository;
use App\Domain\Notification\NotificationRoutes;
use App\Domain\Pricing\PricingConfigRepository;
use App\Domain\Pricing\PricingConfigRoutes;
use App\Domain\Pricing\PricingRepository;
use App\Domain\Pricing\PricingRoutes;
use App\Domain\Realisation\AdminRealisationImageRoutes;
use App\Domain\Realisation\AdminRealisationRoutes;
use App\Domain\Realisation\RealisationImageRepository;
use App\Domain\Realisation\RealisationRepository;
use App\Domain\Realisation\RealisationRoutes;
use App\Domain\Review\ReviewRepository;
use App\Domain\Review\ReviewRoutes;
use App\Domain\Sample\AdminSampleRoutes;
use App\Domain\Sample\SampleAnalyticsRoutes;
use App\Domain\Sample\SampleColorRepository;
use App\Domain\Sample\SampleRoutes;
use App\Domain\Sample\SampleService;
use App\Domain\Sample\SampleTypeRepository;
use App\Domain\Showroom\ShowroomRepository;
use App\Domain\Showroom\ShowroomRoutes;
use App\Http\RouteCollection;

final class ContentRouteRegistry
{
    public static function register(RouteCollection $routes, Settings $settings, Connection $db, AdminRepository $admins): void
    {
        (new ReviewRoutes(new ReviewRepository($db)))->register($routes);
        (new ShowroomRoutes(new ShowroomRepository($settings->rootDir . '/backend/data/showrooms.json')))->register($routes);
        $realisations = new RealisationRepository($db);
        $realisationImages = new RealisationImageRepository($db);
        (new RealisationRoutes($realisations, $realisationImages))->register($routes);
        (new AdminRealisationRoutes($realisations))->register($routes);
        (new AdminRealisationImageRoutes($realisationImages))->register($routes);
        (new NotificationRoutes(new NotificationRepository($db)))->register($routes);
        (new AdminNotificationRoutes(new AdminNotificationRepository($db), $admins))->register($routes);
        (new EmailTemplateRoutes(new EmailTemplateRepository($db)))->register($routes);
        (new PricingRoutes(new PricingRepository($db)))->register($routes);
        (new PricingConfigRoutes(new PricingConfigRepository($db)))->register($routes);
        $catalogueItems = new CatalogueRepository($db);
        $catalogueVariations = new CatalogueVariationRepository($db);
        (new CatalogueRoutes($catalogueItems, $catalogueVariations))->register($routes);
        (new AdminCatalogueRoutes($catalogueItems))->register($routes);
        (new AdminCatalogueVariationRoutes($catalogueVariations, $catalogueItems))->register($routes);
        $sampleTypes = new SampleTypeRepository($db);
        $sampleColors = new SampleColorRepository($db);
        $sampleService = new SampleService($sampleTypes, $sampleColors);
        (new SampleRoutes($sampleService))->register($routes);
        (new AdminSampleRoutes($sampleService, $sampleTypes, $sampleColors))->register($routes);
        (new SampleAnalyticsRoutes($db))->register($routes);
        (new PublicConfigRoutes(Env::load($settings->rootDir . '/.env')))->register($routes);
    }
}
