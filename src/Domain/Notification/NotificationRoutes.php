<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Domain\Shared\DomainException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class NotificationRoutes
{
    public function __construct(private readonly NotificationRepository $notifications) {}

    public function register(RouteCollection $routes): void
    {
        $script = $routes->script('notifications/index', [new CustomerGuard()]);

        $script->get(null, function (Request $r, Session $s): Response {
            $customerId = (int) $s->customerId();
            $unreadOnly = $r->queryString('unread_only') === 'true';
            $limit = (int) ($r->queryString('limit') ?? 50);
            return Response::json([
                'notifications' => $this->notifications->forCustomer($customerId, $unreadOnly, $limit),
                'unread_count' => $this->notifications->countUnread($customerId),
            ]);
        });

        $script->put('/read-all', function (Request $r, Session $s): Response {
            $this->notifications->markAllAsRead((int) $s->customerId());
            return Response::json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
        });

        $script->put('/{id}/read', function (Request $r, Session $s): Response {
            $this->notifications->markAsRead((int) $r->param('id'), (int) $s->customerId());
            return Response::json(['success' => true, 'message' => 'Notification marquée comme lue']);
        });

        $script->delete(null, function (Request $r, Session $s): Response {
            $id = $r->queryString('id');
            if ($id === null) {
                throw new DomainException('ID de notification requis');
            }
            $this->notifications->delete((int) $id, (int) $s->customerId());
            return Response::json(['success' => true, 'message' => 'Notification supprimée']);
        });
    }
}
