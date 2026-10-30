<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Facade\FacadePriceCalculator;
use App\Domain\Shared\DomainException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class CartFacadeRoutes
{
    public function __construct(
        private readonly CartFacadeRepository $facades,
        private readonly FacadePriceCalculator $prices,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('cart/facades', [new CustomerGuard()]);

        $script->get(null, function (Request $r, Session $s): Response {
            $items = $this->facades->items((int) $s->customerId());
            return Response::json(['items' => $items, 'total' => $this->facades->total($items), 'count' => count($items)]);
        });

        $script->post(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['config']) || !is_array($data['config'])) {
                throw new DomainException('config et price requis');
            }
            $quantity = CartQuantity::forAdd($data['quantity'] ?? 1);
            $id = $this->facades->add((int) $s->customerId(), $data['config'], $this->prices->unitPrice($data['config']), $quantity);
            return Response::json(['success' => true, 'message' => 'Façade ajoutée au panier', 'id' => (string) $id], 201);
        });

        $script->put(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['id']) || !isset($data['quantity'])) {
                throw new DomainException('id et quantity requis');
            }
            $quantity = CartQuantity::forUpdate($data['quantity']);
            $quantity === 0
                ? $this->facades->remove((int) $s->customerId(), (int) $data['id'])
                : $this->facades->updateQuantity((int) $s->customerId(), (int) $data['id'], $quantity);
            return Response::json(['success' => true, 'message' => 'Quantité mise à jour']);
        });

        $script->delete(null, function (Request $r, Session $s): Response {
            $id = $r->queryString('id');
            $this->facades->remove((int) $s->customerId(), $id === null ? null : (int) $id);
            return Response::json(['success' => true, 'message' => $id === null ? 'Panier de façades vidé' : 'Façade retirée du panier']);
        });
    }
}
