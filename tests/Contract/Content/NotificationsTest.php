<?php

declare(strict_types=1);

namespace Tests\Contract\Content;

use Tests\Support\ContractTestCase;
use Tests\Support\DatabaseUrl;

final class NotificationsTest extends ContractTestCase
{
    public function testAdminNotificationsRequireSession(): void
    {
        $this->assertSnapshot('notifications.admin.unauthorized', $this->client()->get('/backend/api/admin/notifications.php'));
    }

    public function testAdminNotifications(): void
    {
        $admin = $this->admin();
        $this->seedAdminNotification();
        $list = $admin->get('/backend/api/admin/notifications.php');
        $this->assertSnapshot('notifications.admin.list', $list);
        self::assertNotEmpty($list->data()['notifications']);
        self::assertArrayHasKey('unread_count', $list->data());
        $this->assertSnapshot('notifications.admin.unread', $admin->get('/backend/api/admin/notifications.php?unread=true'));
        $this->assertSnapshot('notifications.admin.limit', $admin->get('/backend/api/admin/notifications.php?limit=1'));
        $first = $list->data()['notifications'][0];
        $this->assertSnapshot('notifications.admin.read-one', $admin->put('/backend/api/admin/notifications.php', ['notification_id' => $first['id'], 'mark_as_read' => true]));
        $this->assertSnapshot('notifications.admin.read-all', $admin->put('/backend/api/admin/notifications.php', ['mark_all_as_read' => true]));
    }

    public function testCustomerNotifications(): void
    {
        $this->assertSnapshot('notifications.customer.unauthorized', $this->client()->get('/backend/api/notifications/index.php'));
        $this->assertSnapshot('notifications.customer.list', $this->customer()->get('/backend/api/notifications/index.php'));
    }

    private function seedAdminNotification(): void
    {
        $pdo = DatabaseUrl::connect(DatabaseUrl::fromEnv());
        $pdo->exec('DELETE FROM admin_notifications');
        $pdo->exec("INSERT INTO admin_notifications (admin_id, type, message, related_id)
                    SELECT id, 'new_order', 'Nouvelle commande de test', NULL FROM admins");
    }
}
