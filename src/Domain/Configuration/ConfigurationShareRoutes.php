<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Http\ErrorStyle;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\RateLimit\RateLimiter;

final class ConfigurationShareRoutes
{
    public function __construct(
        private readonly ConfigurationShareService $shares,
        private readonly ConfigurationRequestService $requests,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $share = $routes->script('configurations/share', errorStyle: ErrorStyle::Success);
        $share->post(null, function (Request $r): Response {
            $code = $this->shares->create($r->json(), RateLimiter::clientIp($_SERVER));
            return Response::json(['success' => true, 'code' => $code], 201);
        });
        $share->get(null, fn(Request $r): Response => Response::json([
            'success' => true,
            'share' => $this->shares->open($r->queryString('code') ?? ''),
        ]));

        $routes->script('configurations/request', errorStyle: ErrorStyle::Success)->post(null, function (Request $r): Response {
            $this->requests->submit($r->json(), RateLimiter::clientIp($_SERVER));
            return Response::json(['success' => true, 'message' => 'Votre demande a été envoyée']);
        });
    }
}
