<?php

declare(strict_types=1);

namespace App;

use App\Config\Settings;
use App\Db\Connection;
use App\Domain\Admin\AdminAccountsRoutes;
use App\Domain\Admin\AdminAccountsService;
use App\Domain\Admin\AdminAuthRoutes;
use App\Domain\Admin\AdminAuthService;
use App\Domain\Admin\AdminRepository;
use App\Domain\Auth\LegacyAuthRoutes;
use App\Domain\Auth\LegacyAuthService;
use App\Domain\Auth\LegacyUserRepository;
use App\Domain\Auth\LegacyUsersAdminRoutes;
use App\Domain\Auth\LegacyUsersAdminService;
use App\Domain\Catalogue\AdminCatalogueRoutes;
use App\Domain\Catalogue\AdminCatalogueVariationRoutes;
use App\Domain\Catalogue\CatalogueRepository;
use App\Domain\Catalogue\CatalogueRoutes;
use App\Domain\Catalogue\CatalogueVariationRepository;
use App\Domain\Category\CategoryRepository;
use App\Domain\Category\CategoryRoutes;
use App\Domain\Category\CategoryService;
use App\Domain\Customer\CustomerAuthRoutes;
use App\Domain\Customer\CustomerAuthService;
use App\Domain\Customer\CustomerProfileRoutes;
use App\Domain\Customer\CustomerProfileService;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\CustomerVerificationRepository;
use App\Domain\EmailTemplate\EmailTemplateRepository;
use App\Domain\EmailTemplate\EmailTemplateRoutes;
use App\Domain\Model\ModelRepository;
use App\Domain\Model\ModelRoutes;
use App\Domain\Model\ModelService;
use App\Domain\Model\TemplateRoutes;
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
use App\Domain\System\AdminCreationRoutes;
use App\Domain\System\SystemRoutes;
use App\Http\RouteCollection;
use App\Infrastructure\Mail\LegacyEmailGateway;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Lib\SystemClock;

final class RouteRegistry
{
    public static function register(RouteCollection $routes, Settings $settings, Connection $db): void
    {
        $admins = new AdminRepository($db);
        $legacyUsers = new LegacyUserRepository($db);
        $rateLimiter = new RateLimiter($db);
        (new SystemRoutes(new SystemClock()))->register($routes);
        (new AdminCreationRoutes($admins, $settings->backupApiKey))->register($routes);
        (new AdminAuthRoutes(new AdminAuthService($admins, $rateLimiter)))->register($routes);
        (new AdminAccountsRoutes(new AdminAccountsService($db, $admins)))->register($routes);
        (new LegacyAuthRoutes(new LegacyAuthService($legacyUsers)))->register($routes);
        (new LegacyUsersAdminRoutes(new LegacyUsersAdminService($legacyUsers, $admins)))->register($routes);
        $customers = new CustomerRepository($db);
        $verifications = new CustomerVerificationRepository($db);
        $mail = new LegacyEmailGateway($settings->rootDir);
        (new CustomerAuthRoutes(new CustomerAuthService($customers, $verifications, $mail, $settings->frontendUrl)))->register($routes);
        (new CustomerProfileRoutes(new CustomerProfileService($customers)))->register($routes);
        (new CategoryRoutes(new CategoryService(new CategoryRepository($db))))->register($routes);
        (new ModelRoutes(new ModelService(new ModelRepository($db))))->register($routes);
        (new TemplateRoutes($db))->register($routes);
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
        (new AdminCatalogueVariationRoutes($catalogueVariations))->register($routes);
        $sampleTypes = new SampleTypeRepository($db);
        $sampleColors = new SampleColorRepository($db);
        $sampleService = new SampleService($sampleTypes, $sampleColors);
        (new SampleRoutes($sampleService))->register($routes);
        (new AdminSampleRoutes($sampleService, $sampleTypes, $sampleColors))->register($routes);
        (new SampleAnalyticsRoutes($db))->register($routes);
    }
}
