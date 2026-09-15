<?php

declare(strict_types=1);

namespace App\Domain\QuoteRequest;

use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class QuoteRequestRoutes
{
    public function __construct(private readonly QuoteRequestService $service) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('quote-request/index', errorStyle: ErrorStyle::Success);
        $script->post(null, function (Request $r): Response {
            $data = $this->service->submit($r->form, $r->files);
            return Response::json(['success' => true, 'message' => 'Votre demande de devis a été envoyée avec succès', 'data' => $data]);
        });
        $script->get(null, fn(): Response => Response::json(['success' => true, 'data' => $this->service->recent(50)]), [new AdminGuard()]);
    }
}
