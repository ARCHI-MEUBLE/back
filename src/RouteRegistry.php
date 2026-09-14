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
use App\Domain\Category\CategoryRepository;
use App\Domain\Category\CategoryRoutes;
use App\Domain\Category\CategoryService;
use App\Domain\Customer\CustomerAuthRoutes;
use App\Domain\Customer\CustomerAuthService;
use App\Domain\Customer\CustomerProfileRoutes;
use App\Domain\Customer\CustomerProfileService;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\CustomerVerificationRepository;
use App\Domain\Model\ModelRepository;
use App\Domain\Model\ModelRoutes;
use App\Domain\Model\ModelService;
use App\Domain\Model\TemplateRoutes;
use App\Domain\System\AdminCreationRoutes;
use App\Domain\System\SystemRoutes;
use App\Http\RouteCollection;
use App\Infrastructure\Mail\LegacyEmailGateway;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Lib\SystemClock;

final class RouteRegistry
{
    public static function register(RouteCollection $routes, Settings $settings, Connection $db): AdminRepository
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
        return $admins;
    }
}
