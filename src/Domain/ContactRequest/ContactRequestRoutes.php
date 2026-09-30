<?php

declare(strict_types=1);

namespace App\Domain\ContactRequest;

use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class ContactRequestRoutes
{
    public function __construct(private readonly ContactRequestService $service) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('contact-request/index', errorStyle: ErrorStyle::Success);
        $script->post(null, function (Request $r): Response {
            $data = $this->service->submit($r->jsonOrEmpty());
            return Response::json(['success' => true, 'message' => 'Votre message a été envoyé avec succès', 'data' => $data]);
        });
        $script->get(null, fn(): Response => Response::json(['success' => true, 'data' => $this->service->recent(50)]), [new AdminGuard()]);
    }
}
