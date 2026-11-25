<?php

declare(strict_types=1);

namespace App;

use App\Db\Connection;
use App\Domain\Cart\CartCatalogueRepository;
use App\Domain\Cart\CartCatalogueRoutes;
use App\Domain\Cart\CartFacadeRepository;
use App\Domain\Cart\CartFacadeRoutes;
use App\Domain\Cart\CartRepository;
use App\Domain\Cart\CartRoutes;
use App\Domain\Cart\CartSampleRepository;
use App\Domain\Cart\CartSampleRoutes;
use App\Domain\Cart\CartShipping;
use App\Domain\Cart\CartShippingRoutes;
use App\Domain\Facade\FacadePriceCalculator;
use App\Domain\Handle\HandleModelRepository;
use App\Domain\Pricing\FurniturePricing;
use App\Domain\Pricing\PricingConfigRepository;
use App\Http\RouteCollection;

final class CartRouteRegistry
{
    public static function register(RouteCollection $routes, Connection $db): void
    {
        $cart = new CartRepository($db);
        $samples = new CartSampleRepository($db);
        $catalogue = new CartCatalogueRepository($db);
        $facades = new CartFacadeRepository($db);
        (new CartRoutes($cart))->register($routes);
        (new CartSampleRoutes($samples))->register($routes);
        (new CartCatalogueRoutes($catalogue))->register($routes);
        (new CartFacadeRoutes($facades, new FacadePriceCalculator($db)))->register($routes);
        $shipping = new CartShipping(new FurniturePricing(new PricingConfigRepository($db), new HandleModelRepository($db)));
        (new CartShippingRoutes($shipping, $cart, $facades, $catalogue, $samples))->register($routes);
    }
}
