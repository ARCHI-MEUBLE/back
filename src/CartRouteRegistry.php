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
use App\Http\RouteCollection;

final class CartRouteRegistry
{
    public static function register(RouteCollection $routes, Connection $db): void
    {
        (new CartRoutes(new CartRepository($db)))->register($routes);
        (new CartSampleRoutes(new CartSampleRepository($db)))->register($routes);
        (new CartCatalogueRoutes(new CartCatalogueRepository($db)))->register($routes);
        (new CartFacadeRoutes(new CartFacadeRepository($db)))->register($routes);
    }
}
