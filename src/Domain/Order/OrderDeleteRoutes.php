<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Domain\Shared\NotFoundException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class OrderDeleteRoutes
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('orders/delete', [new CustomerGuard()])->post(null, function (Request $r, Session $s): Response {
            $data = $r->jsonOrEmpty();
            if (!isset($data['order_id']) || $data['order_id'] === '') {
                throw new DomainException('ID de commande manquant');
            }
            $orderId = (int) $data['order_id'];
            $order = $this->orders->findById($orderId);
            if ($order === null) {
                throw new NotFoundException('Commande introuvable');
            }
            if ((int) $order['customer_id'] !== (int) $s->customerId()) {
                throw new ForbiddenException('Accès refusé');
            }
            if ($order['payment_status'] === 'paid') {
                throw new DomainException('Impossible de supprimer une commande payée');
            }
            $this->orders->delete($orderId);
            return Response::json(['success' => true, 'message' => 'Commande supprimée avec succès']);
        });
    }
}
