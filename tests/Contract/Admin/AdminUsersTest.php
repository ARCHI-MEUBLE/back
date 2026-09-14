<?php

declare(strict_types=1);

namespace Tests\Contract\Admin;

use Tests\Support\Accounts;
use Tests\Support\ContractTestCase;
use Tests\Support\DatabaseUrl;

final class AdminUsersTest extends ContractTestCase
{
    public function testPasswordResetAndDeleteByAdmin(): void
    {
        $email = Accounts::uniqueEmail('gere');
        Accounts::registerVerifiedCustomer($this->client(), $email, 'Gere#2026');
        $pdo = DatabaseUrl::connect(DatabaseUrl::fromEnv());
        $statement = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
        $statement->execute([$email]);
        $id = (int) $statement->fetchColumn();
        $admin = $this->admin();
        $this->assertSnapshot('admin.users.password', $admin->put('/backend/api/admin-users.php', ['id' => $id, 'type' => 'customer', 'newPassword' => 'Reset#2026']));
        $this->assertSnapshot('admin.users.password.login-after', $this->client()->post('/backend/api/customers/login.php', ['email' => $email, 'password' => 'Reset#2026']));
        $this->assertSnapshot('admin.users.password.invalid', $admin->put('/backend/api/admin-users.php', ['id' => $id]));
        $this->assertSnapshot('admin.users.delete', $admin->delete('/backend/api/admin-users.php', ['id' => $id, 'type' => 'customer']));
        $this->assertSnapshot('admin.users.delete.missing', $admin->delete('/backend/api/admin-users.php', ['id' => $id, 'type' => 'customer']));
    }

    public function testUsersApiForm(): void
    {
        $this->assertSnapshot('users.list.unauthorized', $this->client()->get('/api/users'));
        $this->assertSnapshot('users.list', $this->admin()->get('/api/users'));
    }

    public function testAdminRegisterIsDisabled(): void
    {
        $this->assertSnapshot('admin.register.disabled', $this->client()->post('/backend/api/admin/register.php', ['email' => 'x@y.z', 'password' => 'p']));
    }
}
