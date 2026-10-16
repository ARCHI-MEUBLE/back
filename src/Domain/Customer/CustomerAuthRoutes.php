<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Domain\Customer\CustomerMapper as Mapper;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Infrastructure\RateLimit\RateLimiter;

final class CustomerAuthRoutes
{
    public function __construct(
        private readonly CustomerAuthService $service,
        private readonly CustomerPasswordResetService $passwordReset,
    ) {}

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
            try {
                return Response::json(Mapper::login($this->service->login($data['email'], $data['password'], RateLimiter::clientIp($_SERVER), $s)));
            } catch (EmailNotVerifiedException $e) {
                return Response::json(['success' => false, 'error' => $e->getMessage(), 'requiresVerification' => true, 'email' => $e->email], $e->status);
            }
        });

        $routes->script('customers/register')->post(null, function (Request $r): Response {
            $data = CustomerSchemas::validateRegistration($r->json());
            $outcome = $this->service->register($data, RateLimiter::clientIp($_SERVER));
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
            $this->passwordReset->forgotPassword($data['email'], RateLimiter::clientIp($_SERVER));
            return Response::json(['success' => true, 'message' => 'Si cet email est associé à un compte, un lien de réinitialisation a été envoyé.']);
        });

        $routes->script('customers/reset-password')->post(null, function (Request $r): Response {
            $data = CustomerSchemas::resetPassword()->validate($r->json());
            $this->passwordReset->resetPassword($data['token'], $data['password']);
            return Response::json(['success' => true, 'message' => 'Votre mot de passe a été mis à jour avec succès.']);
        });
    }
}
