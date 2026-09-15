<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class ConfigurationSaveRoutes
{
    public function __construct(private readonly ConfigurationSaveService $service) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('configurations/save')->post(null, function (Request $r, Session $s): Response {
            $result = $this->service->save($r->json(), $s);
            return Response::json($result->payload, $result->status);
        });
    }
}
