<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Auth\LegacyAuthMapper as Mapper;
use App\Domain\Shared\NotFoundException;
use App\Http\LegacyActionRouter;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Lib\Validation\Field;
use App\Lib\Validation\Schema;

final class LegacyAuthRoutes
{
    public function __construct(private readonly LegacyAuthService $service) {}

    public function register(RouteCollection $routes): void
    {
        LegacyActionRouter::register($routes, 'auth', [], $this->dispatch());
    }

    private function dispatch(): \Closure
    {
        return function (string $method, string $action, Request $request, Session $session): Response {
            if ($method === 'POST' && $action === 'login') {
                $data = self::credentials()->validate($request->json());
                return Response::json(Mapper::loggedIn($this->service->login($data['email'], $data['password'], $session)));
            }
            if ($method === 'POST' && $action === 'register') {
                $data = self::registration()->validate($request->json());
                return Response::json(Mapper::registered($this->service->register($data['email'], $data['password'], $data['name'], $session)), 201);
            }
            if ($method === 'GET' && $action === 'session') {
                return Response::json(Mapper::session($this->service->current($session)));
            }
            if ($method === 'DELETE' && $action === 'logout') {
                $session->destroy();
                return Response::json(['success' => true]);
            }
            if ($method === 'PUT' && $action === 'password') {
                $this->service->current($session);
                $data = self::passwordChange()->validate($request->json());
                $this->service->changePassword($data['currentPassword'], $data['newPassword'], $session);
                return Response::json(['success' => true]);
            }
            throw new NotFoundException('Endpoint non trouvé');
        };
    }

    private static function credentials(): Schema
    {
        return Schema::object([
            'email' => Field::string()->required('Email et mot de passe requis'),
            'password' => Field::string()->required('Email et mot de passe requis'),
        ]);
    }

    private static function registration(): Schema
    {
        return Schema::object([
            'email' => Field::string()->required('Email et mot de passe requis'),
            'password' => Field::string()->required('Email et mot de passe requis'),
            'name' => Field::string()->nullable(),
        ]);
    }

    private static function passwordChange(): Schema
    {
        return Schema::object([
            'currentPassword' => Field::string()->required('Mots de passe requis'),
            'newPassword' => Field::string()->required('Mots de passe requis'),
        ]);
    }
}
