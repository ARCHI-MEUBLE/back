<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Shared\DomainException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class CartRoutes
{
    public function __construct(private readonly CartRepository $cart) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('cart/index', [new CustomerGuard()]);

        $script->get(null, function (Request $r, Session $s): Response {
            $customerId = (int) $s->customerId();
            $count = $this->cart->count($customerId);
            return Response::json([
                'items' => $this->cart->items($customerId),
                'total' => $this->cart->total($customerId),
                'count' => $count,
                'item_count' => $count,
            ]);
        });

        $script->post(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['configuration_id'])) {
                throw new DomainException('configuration_id requis');
            }
            $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;
            $this->cart->addItem((int) $s->customerId(), (int) $data['configuration_id'], $quantity);
            return Response::json(['success' => true, 'message' => 'Ajouté au panier'], 201);
        });

        $script->put(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['configuration_id']) || !isset($data['quantity'])) {
                throw new DomainException('configuration_id et quantity requis');
            }
            $this->cart->updateQuantity((int) $s->customerId(), (int) $data['configuration_id'], (int) $data['quantity']);
            return Response::json(['success' => true, 'message' => 'Quantité mise à jour']);
        });

        $script->delete(null, function (Request $r, Session $s): Response {
            $customerId = (int) $s->customerId();
            $configurationId = $r->queryString('configuration_id');
            if ($configurationId !== null) {
                $this->cart->removeItem($customerId, (int) $configurationId);
                return Response::json(['success' => true, 'message' => 'Retiré du panier']);
            }
            $this->cart->clear($customerId);
            return Response::json(['success' => true, 'message' => 'Panier vidé']);
        });
    }
}
