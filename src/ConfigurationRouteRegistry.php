<?php

declare(strict_types=1);

namespace App;

use App\Config\Settings;
use App\Db\Connection;
use App\Domain\Configuration\AdminConfigurationRoutes;
use App\Domain\Configuration\AdminConfigurationStatusRoutes;
use App\Domain\Configuration\ConfigurationApiRoutes;
use App\Domain\Configuration\ConfigurationDxfRoutes;
use App\Domain\Configuration\ConfigurationRepository;
use App\Domain\Configuration\ConfigurationSaveRoutes;
use App\Domain\Configuration\ConfigurationSaveService;
use App\Domain\Configuration\CustomerConfigurationRoutes;
use App\Domain\Configuration\GenerateRoutes;
use App\Domain\Customer\CustomerRepository;
use App\Http\RouteCollection;
use App\Infrastructure\Mail\EmailGatewayFactory;
use App\Infrastructure\Python\ProcOpenRunner;
use App\Lib\Logger;

final class ConfigurationRouteRegistry
{
    public static function register(RouteCollection $routes, Settings $settings, Connection $db, Logger $logger): void
    {
        $configurations = new ConfigurationRepository($db);
        (new ConfigurationApiRoutes($configurations))->register($routes);
        (new CustomerConfigurationRoutes($configurations))->register($routes);
        (new AdminConfigurationRoutes($configurations))->register($routes);
        (new AdminConfigurationStatusRoutes($configurations))->register($routes);
        (new ConfigurationDxfRoutes($db, $settings->rootDir))->register($routes);
        $mail = EmailGatewayFactory::create($settings, $db);
        $saveService = new ConfigurationSaveService($configurations, new CustomerRepository($db), $mail);
        (new ConfigurationSaveRoutes($saveService))->register($routes);
        (new GenerateRoutes(
            new ProcOpenRunner(),
            $logger,
            $settings->paths->modelsDir,
            $settings->python->scriptPath,
            $settings->python->binary,
            $settings->python->timeoutSeconds,
        ))->register($routes);
    }
}
