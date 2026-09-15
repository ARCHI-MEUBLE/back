<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Shared\DomainException;
use App\Http\ErrorStyle;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class CartCatalogueRoutes
{
    public function __construct(private readonly CartCatalogueRepository $catalogue) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('cart/catalogue', [new CustomerGuard()], ErrorStyle::Success);

        $script->get(null, function (Request $r, Session $s): Response {
            return Response::json(['success' => true, 'items' => $this->catalogue->items((int) $s->customerId())]);
        });

        $script->post(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['catalogue_item_id'])) {
                throw new DomainException('catalogue_item_id requis');
            }
            $variationId = isset($data['variation_id']) ? (int) $data['variation_id'] : null;
            $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;
            $this->catalogue->add((int) $s->customerId(), (int) $data['catalogue_item_id'], $variationId, $quantity);
            return Response::json(['success' => true, 'message' => 'Article ajouté au panier']);
        });

        $script->put(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['id']) || !isset($data['quantity'])) {
                throw new DomainException('id et quantity requis');
            }
            $this->catalogue->applyQuantity((int) $s->customerId(), (int) $data['id'], (int) $data['quantity']);
            return Response::json(['success' => true, 'message' => 'Quantité mise à jour']);
        });

        $script->delete(null, function (Request $r, Session $s): Response {
            $id = $r->queryString('id');
            $this->catalogue->remove((int) $s->customerId(), $id === null ? null : (int) $id);
            return Response::json(['success' => true, 'message' => $id === null ? 'Panier vidé' : 'Article retiré du panier']);
        });
    }
}
