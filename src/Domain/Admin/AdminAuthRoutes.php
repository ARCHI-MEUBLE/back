<?php

declare(strict_types=1);

namespace App\Domain\Admin;

use App\Domain\Admin\AdminMapper as Mapper;
use App\Domain\Shared\NotFoundException;
use App\Http\LegacyActionRouter;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Infrastructure\RateLimit\RateLimiter;

final class AdminAuthRoutes
{
    public function __construct(private readonly AdminAuthService $service) {}

    public function register(RouteCollection $routes): void
    {
        LegacyActionRouter::register($routes, 'admin-auth', [], $this->strictDispatch());
        LegacyActionRouter::register($routes, 'admin', [], $this->legacyDispatch());
        LegacyActionRouter::register($routes, 'admin/register', [], static fn(): Response => Response::json(['error' => 'Inscription desactivee'], 403));
    }

    private function strictDispatch(): \Closure
    {
        return function (string $method, string $action, Request $request, Session $session): Response {
            if ($method === 'POST' && $action === 'login') {
                $data = AdminSchemas::login()->validate($request->json());
                return Response::json(Mapper::login($this->service->loginRateLimited($data['email'], $data['password'], RateLimiter::clientIp($_SERVER), $session)));
            }
            if ($method === 'POST' && $action === 'logout') {
                $session->destroy();
                return Response::json(['success' => true]);
            }
            if ($method === 'GET' && $action === 'session') {
                $admin = $this->service->currentRich($session);
                return Response::json($admin === null ? ['admin' => null] : Mapper::sessionRich($admin, $session->int('admin_id')));
            }
            throw new NotFoundException('Endpoint non trouvé');
        };
    }

    private function legacyDispatch(): \Closure
    {
        return function (string $method, string $action, Request $request, Session $session): Response {
            if ($method === 'POST' && $action === 'login') {
                $data = AdminSchemas::login()->validate($request->json());
                return Response::json(Mapper::login($this->service->loginLegacy($data['email'], $data['password'], $session)));
            }
            if ($method === 'POST' && $action === 'logout') {
                $session->destroy();
                return Response::json(['success' => true]);
            }
            if ($method === 'GET' && $action === 'session') {
                return Response::json(Mapper::sessionShort($this->service->currentStrict($session)));
            }
            throw new NotFoundException('Endpoint non trouvé');
        };
    }
}
