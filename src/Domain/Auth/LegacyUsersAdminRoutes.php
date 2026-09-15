<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Http\ErrorStyle;
use App\Http\Guard\AdminSessionForbiddenGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Lib\Validation\Field;
use App\Lib\Validation\Schema;

final class LegacyUsersAdminRoutes
{
    public function __construct(private readonly LegacyUsersAdminService $service) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('users', [new AdminSessionForbiddenGuard()], ErrorStyle::Plain)
            ->get(null, fn(): Response => Response::json($this->service->list()))
            ->put(null, function (Request $request): Response {
                $data = self::resetSchema()->validate($request->json());
                $this->service->resetPassword($data['id'], $data['type'], $data['newPassword']);
                return Response::json(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
            })
            ->delete(null, function (Request $request): Response {
                $data = self::deleteSchema()->validate($request->json());
                $this->service->delete($data['id'], $data['type']);
                return Response::json(['success' => true, 'message' => 'Utilisateur supprimé']);
            });
    }

    private static function resetSchema(): Schema
    {
        return Schema::object([
            'id' => Field::any()->required('ID, type et nouveau mot de passe requis'),
            'type' => Field::string()->required('ID, type et nouveau mot de passe requis'),
            'newPassword' => Field::string()->minLength(6, 'Le mot de passe doit contenir au moins 6 caractères')->required('ID, type et nouveau mot de passe requis'),
        ]);
    }

    private static function deleteSchema(): Schema
    {
        return Schema::object([
            'id' => Field::any()->required('ID et type requis'),
            'type' => Field::string()->required('ID et type requis'),
        ]);
    }
}
