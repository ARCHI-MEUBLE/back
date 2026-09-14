<?php

declare(strict_types=1);

namespace App;

use App\Config\Env;
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
use App\Domain\System\AdminCreationRoutes;
use App\Domain\System\SystemRoutes;
use App\Http\Kernel;
use App\Http\LegacyScriptHandler;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\ErrorHandlerMiddleware;
use App\Http\Middleware\RequestLogMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SessionMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Router;
use App\Http\Runtime;
use App\Http\StaticFileHandler;
use App\Infrastructure\Mail\LegacyEmailGateway;
use App\Lib\Logger;
use App\Lib\SystemClock;

final class App
{
    private function __construct(
        private readonly Settings $settings,
        private readonly Logger $logger,
    ) {}

    public static function boot(string $root): self
    {
        $env = Env::load($root . '/.env');
        $settings = Settings::fromEnv($env, $root);
        $logger = new Logger(fopen('php://stderr', 'w'));
        Runtime::configure($logger);
        return new self($settings, $logger);
    }

    public function run(): void
    {
        $request = Request::fromGlobals();
        $legacy = new LegacyScriptHandler($this->settings->rootDir);
        $kernel = $this->kernel($legacy);
        $response = $kernel->handle($request);
        if ($response !== null) {
            $response->send();
            return;
        }
        if ($legacy->handle($request->path)) {
            return;
        }
        Response::json(['success' => false, 'error' => 'Endpoint non trouvé', 'requested' => $legacy->endpoint($request->path)], 404)->send();
    }

    public function kernel(?LegacyScriptHandler $legacy = null): Kernel
    {
        $db = Connection::fromUrl($this->settings->databaseUrl);
        $admins = new AdminRepository($db);
        $legacyUsers = new LegacyUserRepository($db);
        $rateLimiter = new \App\Infrastructure\RateLimit\RateLimiter($db);
        $routes = new RouteCollection();
        (new SystemRoutes(new SystemClock()))->register($routes);
        (new AdminCreationRoutes($admins, $this->settings->backupApiKey))->register($routes);
        (new AdminAuthRoutes(new AdminAuthService($admins, $rateLimiter)))->register($routes);
        (new AdminAccountsRoutes(new AdminAccountsService($db, $admins)))->register($routes);
        (new LegacyAuthRoutes(new LegacyAuthService($legacyUsers)))->register($routes);
        (new LegacyUsersAdminRoutes(new LegacyUsersAdminService($legacyUsers, $admins)))->register($routes);
        $customers = new CustomerRepository($db);
        $verifications = new CustomerVerificationRepository($db);
        $mail = new LegacyEmailGateway($this->settings->rootDir);
        (new CustomerAuthRoutes(new CustomerAuthService($customers, $verifications, $mail, $this->settings->frontendUrl)))->register($routes);
        (new CustomerProfileRoutes(new CustomerProfileService($customers)))->register($routes);
        (new CategoryRoutes(new CategoryService(new CategoryRepository($db))))->register($routes);
        return new Kernel(
            new Router($routes, $legacy),
            new StaticFileHandler($this->settings->paths),
            [
                new RequestLogMiddleware($this->logger, new SystemClock()),
                new ErrorHandlerMiddleware($this->logger),
                new SecurityHeadersMiddleware(),
                new CorsMiddleware($this->settings->cors),
                new SessionMiddleware($this->settings->session),
            ],
        );
    }

    public function settings(): Settings
    {
        return $this->settings;
    }
}
