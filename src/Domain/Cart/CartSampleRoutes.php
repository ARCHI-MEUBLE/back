<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Shared\DomainException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class CartSampleRoutes
{
    public function __construct(private readonly CartSampleRepository $samples) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('cart/samples', [new CustomerGuard()]);

        $script->get(null, function (Request $r, Session $s): Response {
            $items = $this->samples->items((int) $s->customerId(), $r->host);
            return Response::json(['success' => true, 'items' => $items, 'count' => count($items)]);
        });

        $script->post(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['sample_color_id'])) {
                throw new DomainException('sample_color_id requis');
            }
            $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;
            $itemId = $this->samples->add((int) $s->customerId(), (int) $data['sample_color_id'], $quantity);
            return Response::json(['success' => true, 'message' => 'Échantillon ajouté au panier', 'item_id' => $itemId], 201);
        });

        $script->put(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['item_id']) || !isset($data['quantity'])) {
                throw new DomainException('item_id et quantity requis');
            }
            $this->samples->updateQuantity((int) $s->customerId(), (int) $data['item_id'], (int) $data['quantity']);
            return Response::json(['success' => true, 'message' => 'Quantité mise à jour']);
        });

        $script->delete(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['item_id'])) {
                throw new DomainException('item_id requis');
            }
            $this->samples->remove((int) $s->customerId(), (int) $data['item_id']);
            return Response::json(['success' => true, 'message' => 'Échantillon retiré du panier']);
        });
    }
}
