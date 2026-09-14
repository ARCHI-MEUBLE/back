<?php

declare(strict_types=1);

namespace App;

use App\Config\Env;
use App\Config\Settings;
use App\Db\Connection;
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
        $routes = new RouteCollection();
        $admins = RouteRegistry::register($routes, $this->settings, $db);
        ContentRouteRegistry::register($routes, $this->settings, $db, $admins);
        CommerceRouteRegistry::register($routes, $this->settings, $db);
        ConfigurationRouteRegistry::register($routes, $this->settings, $db, $this->logger);
        CartRouteRegistry::register($routes, $db);
        OrderRouteRegistry::register($routes, $this->settings, $db);
        PaymentRouteRegistry::register($routes, $this->settings, $db, $this->logger);
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
