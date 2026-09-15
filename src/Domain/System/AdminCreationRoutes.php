<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Domain\Admin\AdminRepository;
use App\Domain\Shared\ForbiddenException;
use App\Domain\Shared\IntegrationNotConfiguredException;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Lib\Validation\Field;
use App\Lib\Validation\Schema;

final class AdminCreationRoutes
{
    public function __construct(
        private readonly AdminRepository $admins,
        private readonly string $backupApiKey,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('system/create-admin')->post(null, function (Request $request): Response {
            if ($this->backupApiKey === '') {
                throw new IntegrationNotConfiguredException('Service temporarily unavailable');
            }
            if ($request->queryString('key') !== $this->backupApiKey) {
                throw new ForbiddenException('Cle API invalide');
            }
            $data = self::schema()->validate($request->json());
            $action = $this->admins->upsertByEmail($data['email'], password_hash($data['password'], PASSWORD_BCRYPT), $data['username']);
            return Response::json(['success' => true, 'action' => $action, 'email' => $data['email']]);
        });
    }

    private static function schema(): Schema
    {
        return Schema::object([
            'email' => Field::string()->required('email et password requis'),
            'password' => Field::string()->required('email et password requis'),
            'username' => Field::string()->nullable(),
        ]);
    }
}
