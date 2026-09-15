<?php

declare(strict_types=1);

namespace App\Domain\Admin;

use App\Http\ErrorStyle;
use App\Http\Guard\AdminSessionForbiddenGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class AdminAccountsRoutes
{
    public function __construct(private readonly AdminAccountsService $service) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin-users', [new AdminSessionForbiddenGuard()], ErrorStyle::Plain)
            ->get(null, fn(): Response => Response::json($this->service->list()))
            ->put(null, function (Request $request): Response {
                $data = AdminSchemas::resetPassword()->validate($request->json());
                $this->service->resetPassword($data['id'], $data['type'], $data['newPassword']);
                return Response::json(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
            })
            ->delete(null, function (Request $request): Response {
                $data = AdminSchemas::deleteAccount()->validate($request->json());
                $this->service->delete($data['id'], $data['type']);
                return Response::json(['success' => true, 'message' => 'Compte supprimé avec succès']);
            });
    }
}
