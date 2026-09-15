<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Shared\DomainException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class OrderListRoutes
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('orders/list', [new CustomerGuard()])->get(null, function (Request $r, Session $s): Response {
            $customerId = (int) $s->customerId();
            $id = $r->queryString('id');
            return $id !== null ? $this->one($customerId, (int) $id) : $this->list($r, $customerId);
        });
    }

    private function one(int $customerId, int $id): Response
    {
        $order = $this->orders->findById($id);
        if ($order === null || (int) $order['customer_id'] !== $customerId) {
            throw new DomainException('Commande non trouvée', 404);
        }
        $order['items'] = $this->orders->itemsFor($id);
        $order['samples'] = $this->orders->samplesFor($id);
        $order['catalogue_items'] = $this->orders->catalogueItemsFor($id);
        $order['facade_items'] = $this->orders->facadeItemsFor($id);
        return Response::json(['order' => OrderMapper::toFrontend($order)]);
    }

    private function list(Request $r, int $customerId): Response
    {
        $limit = (int) ($r->queryString('limit') ?? 50);
        $offset = (int) ($r->queryString('offset') ?? 0);
        $orders = array_map(function (array $order): array {
            $order['samples_count'] = count($this->orders->samplesFor((int) $order['id']));
            return OrderMapper::toFrontend($order);
        }, $this->orders->forCustomer($customerId, $limit, $offset));
        return Response::json(['orders' => $orders]);
    }
}
