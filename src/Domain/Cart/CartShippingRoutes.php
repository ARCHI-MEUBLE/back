<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Http\ErrorStyle;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class CartShippingRoutes
{
    public function __construct(
        private readonly CartShipping $shipping,
        private readonly CartRepository $cart,
        private readonly CartFacadeRepository $facades,
        private readonly CartCatalogueRepository $catalogue,
        private readonly CartSampleRepository $samples,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('cart/shipping', [new CustomerGuard()], ErrorStyle::Success)->get(null, function (Request $r, Session $s): Response {
            $customerId = (int) $s->customerId();
            $facades = $this->facades->items($customerId);
            $catalogue = $this->catalogue->items($customerId);
            $samples = $this->samples->items($customerId, $r->host);
            $subtotal = $this->facades->total($facades) + self::lineTotal($catalogue) + self::lineTotal($samples);
            $hasParcelItems = $facades !== [] || $catalogue !== [] || $samples !== [];
            return Response::json(['success' => true, 'data' => $this->shipping->quote($this->cart->items($customerId), $subtotal, $hasParcelItems)]);
        });
    }

    private static function lineTotal(array $rows): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += ((float) ($row['unit_price'] ?? 0)) * ((int) ($row['quantity'] ?? 0));
        }
        return $total;
    }
}
