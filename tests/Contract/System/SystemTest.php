<?php

declare(strict_types=1);

namespace Tests\Contract\System;

use Tests\Support\ContractTestCase;

final class SystemTest extends ContractTestCase
{
    public function testCreateAdminRequiresKey(): void
    {
        $this->assertSnapshot('system.create-admin.forbidden', $this->client()->post('/backend/api/system/create-admin.php', ['email' => 'x@y.z', 'password' => 'p']));
        $this->assertSnapshot('system.create-admin.invalid', $this->client()->post('/backend/api/system/create-admin.php?key=test-backup-key', ['email' => 'x@y.z']));
        $email = 'created-' . bin2hex(random_bytes(3)) . '@archimeuble.com';
        $this->assertSnapshot('system.create-admin.created', $this->client()->post('/backend/api/system/create-admin.php?key=test-backup-key', ['email' => $email, 'password' => 'Created#2026']));
        $this->assertSnapshot('system.create-admin.updated', $this->client()->post('/backend/api/system/create-admin.php?key=test-backup-key', ['email' => $email, 'password' => 'Updated#2026']));
        self::assertSame(200, $this->client()->post('/backend/api/admin-auth/login.php', ['email' => $email, 'password' => 'Updated#2026'])->status);
    }

    public function testDbMaintenanceRequiresKey(): void
    {
        $this->assertSnapshot('system.db-maintenance.forbidden', $this->client()->get('/backend/api/system/db-maintenance.php'));
        $this->assertSnapshot('system.db-maintenance.forbidden', $this->client()->post('/backend/api/system/db-maintenance/create?key=wrong'));
        $this->assertSnapshot('system.db-maintenance.list', $this->client()->get('/backend/api/system/db-maintenance/list?key=test-backup-key'));
    }
}
