<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Domain\Admin\AdminRepository;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Lib\Validation\Field;
use App\Lib\Validation\Schema;

final class AdminCreationRoutes
{
    public function __construct(
        private readonly AdminRepository $admins,
        private readonly BackupAccessGuard $guard,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('system/create-admin')->post(null, function (Request $request): Response {
            $ip = $this->guard->check($request);
            $data = self::schema()->validate($request->json());
            $action = $this->admins->upsertByEmail($data['email'], password_hash($data['password'], PASSWORD_BCRYPT), $data['username']);
            $this->guard->log('CREATE_ADMIN', true, $ip, sprintf('%s %s', $action, $data['email']));
            return Response::json(['success' => true, 'action' => $action, 'email' => $data['email']]);
        });
    }

    private static function schema(): Schema
    {
        return Schema::object([
            'email' => Field::string()->required('email et password requis')->email('Email invalide'),
            'password' => Field::string()->required('email et password requis')->minLength(10, 'Le mot de passe doit contenir au moins 10 caractères'),
            'username' => Field::string()->nullable(),
        ]);
    }
}
