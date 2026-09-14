<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Domain\Admin\AdminRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;

final class AdminNotificationRoutes
{
    public function __construct(
        private readonly AdminNotificationRepository $notifications,
        private readonly AdminRepository $admins,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/notifications', [new AdminGuard()])
            ->get(null, function (Request $r, Session $s): Response {
                $adminId = $this->adminIdFor($s);
                $unreadOnly = $r->queryString('unread') === 'true';
                $notifications = $unreadOnly
                    ? $this->notifications->unread($adminId, 50)
                    : $this->notifications->all($adminId, (int) ($r->queryString('limit') ?? 100), (int) ($r->queryString('offset') ?? 0));
                return Response::json(['notifications' => $notifications, 'unread_count' => $this->notifications->countUnread($adminId)]);
            })
            ->put(null, function (Request $r, Session $s): Response {
                $adminId = $this->adminIdFor($s);
                $data = $r->json();
                if (($data['mark_all_read'] ?? $data['mark_all_as_read'] ?? false)) {
                    $this->notifications->markAllAsRead($adminId);
                } elseif (isset($data['notification_id'])) {
                    $this->notifications->markAsRead((int) $data['notification_id']);
                } elseif (($data['mark_as_read'] ?? false) && isset($data['id'])) {
                    $this->notifications->markAsRead((int) $data['id']);
                } else {
                    throw new DomainException('Paramètre invalide');
                }
                return Response::json(['success' => true, 'message' => 'Notification(s) marquée(s) comme lue(s)']);
            });
    }

    private function adminIdFor(Session $session): int
    {
        $admin = $this->admins->findByEmail((string) $session->string('admin_email'));
        if ($admin === null) {
            throw new UnauthorizedException('Admin non trouvé');
        }
        return $admin->id;
    }
}
