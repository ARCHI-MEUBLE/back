<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class CustomerProfileRoutes
{
    public function __construct(private readonly CustomerProfileService $service) {}

    public function register(RouteCollection $routes): void
    {
        $guard = [new CustomerGuard()];

        $updateHandler = function (Request $r, Session $s): Response {
            $customer = $this->service->updateBasic((int) $s->customerId(), $r->json(), $s);
            return Response::json(['success' => true, 'message' => 'Informations mises à jour avec succès', 'customer' => $customer]);
        };
        $routes->script('customers/update', $guard)->put(null, $updateHandler);
        $routes->script('customers/profile', $guard)->put(null, $updateHandler);

        $routes->script('customers/change-password', $guard)->put(null, function (Request $r, Session $s): Response {
            $data = CustomerSchemas::changePassword('current_password', 'new_password')->validate($r->json());
            $this->service->changePassword((int) $s->customerId(), $data['current_password'], $data['new_password'], 400);
            return Response::json(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
        });

        $routes->script('customers/delete', $guard)->delete(null, function (Request $r, Session $s): Response {
            $this->service->deleteWithoutConfirmation((int) $s->customerId(), $s);
            return Response::json(['success' => true, 'message' => 'Compte supprimé avec succès']);
        });

        $routes->script('account/password', $guard)->put(null, function (Request $r, Session $s): Response {
            $data = CustomerSchemas::changePassword('currentPassword', 'newPassword')->validate($r->json());
            $this->service->changePassword((int) $s->customerId(), $data['currentPassword'], $data['newPassword']);
            return Response::json(['success' => true, 'message' => 'Mot de passe mis à jour avec succès']);
        });

        $routes->script('account/delete', $guard)->delete(null, function (Request $r, Session $s): Response {
            $data = CustomerSchemas::deleteConfirmation()->validate($r->json());
            $this->service->deleteAccount((int) $s->customerId(), $data['password'], $s);
            return Response::json(['success' => true, 'message' => 'Compte supprimé avec succès']);
        });
    }
}
