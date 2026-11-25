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
        $routes->script('pricing/estimate', errorStyle: ErrorStyle::Success)
            ->post(null, function (Request $request): Response {
                $config = $request->json()['config_data'] ?? null;
                if (!is_array($config) || !FurniturePricing::canEstimate($config)) {
                    throw new DomainException('Configuration invalide');
                }
                return Response::json(['success' => true, 'data' => $this->pricing->estimate($config)]);
            });
    }
}
