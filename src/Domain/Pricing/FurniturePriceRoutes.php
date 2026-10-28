<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Shared\DomainException;
use App\Http\ErrorStyle;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class FurniturePriceRoutes
{
    public function __construct(private readonly FurniturePricing $pricing) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('pricing/estimate', errorStyle: ErrorStyle::Success)->post(null, function (Request $r): Response {
            $configuration = $r->json()['config_data'] ?? null;
            if (!is_array($configuration) || !FurniturePricing::canEstimate($configuration)) {
                throw new DomainException('Configuration invalide');
            }
            return Response::json(['success' => true, 'data' => $this->pricing->estimate($configuration)]);
        });
    }
}
