<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Domain\Customer\CustomerMapper as Mapper;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class CustomerAuthRoutes
{
    public function __construct(private readonly CustomerAuthService $service) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('customers/session')
            ->get(null, fn(Request $r, Session $s): Response => Response::json(Mapper::session($this->service->current($s))))
            ->delete(null, function (Request $r, Session $s): Response {
                $s->destroy();
                return Response::json(['success' => true, 'message' => 'Déconnexion réussie']);
            });

        $routes->script('customers/login')->post(null, function (Request $r, Session $s): Response {
            $data = CustomerSchemas::login()->validate($r->json());
            return Response::json(Mapper::login($this->service->login($data['email'], $data['password'], $s)));
        });

        $routes->script('customers/register')->post(null, function (Request $r): Response {
            $data = CustomerSchemas::validateRegistration($r->json());
            $outcome = $this->service->register($data);
            return Response::json(Mapper::registered($outcome), $outcome->isNewAccount ? 201 : 200);
        });

        $routes->script('customers/verify-email')->post(null, function (Request $r, Session $s): Response {
            $data = CustomerSchemas::verifyEmail()->validate($r->json());
            return Response::json(['success' => true, 'message' => 'Email vérifié avec succès. Bienvenue !', 'customer' => $this->service->verifyEmail($data['email'], $data['code'], $s)]);
        });

        $routes->script('customers/resend-code')->post(null, function (Request $r): Response {
            $data = CustomerSchemas::emailOnly('Email requis')->validate($r->json());
            $this->service->resendCode($data['email']);
            return Response::json(['success' => true, 'message' => 'Un nouveau code de vérification a été envoyé à votre adresse email.']);
        });

        $routes->script('customers/forgot-password')->post(null, function (Request $r): Response {
            $data = CustomerSchemas::requiredEmail('Email requis')->validate($r->json());
            $this->service->forgotPassword($data['email']);
            return Response::json(['success' => true, 'message' => 'Si cet email est associé à un compte, un lien de réinitialisation a été envoyé.']);
        });

        $routes->script('customers/reset-password')->post(null, function (Request $r): Response {
            $data = CustomerSchemas::resetPassword()->validate($r->json());
            $this->service->resetPassword($data['token'], $data['password']);
            return Response::json(['success' => true, 'message' => 'Votre mot de passe a été mis à jour avec succès.']);
        });
    }
}
