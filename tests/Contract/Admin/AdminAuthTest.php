<?php

declare(strict_types=1);

namespace Tests\Contract\Admin;

use Tests\Support\ContractTestCase;
use Tests\Support\DbSeeder;

final class AdminAuthTest extends ContractTestCase
{
    public function testLoginRequiresCredentials(): void
    {
        $this->assertSnapshot('admin.login.missing', $this->client()->post('/backend/api/admin-auth/login.php', []));
    }

    public function testLoginRejectsWrongPassword(): void
    {
        $response = $this->client()->post('/backend/api/admin-auth/login.php', [
            'email' => DbSeeder::ADMIN_EMAIL,
            'password' => 'wrong',
        ]);

        $this->assertSnapshot('admin.login.invalid', $response);
    }

    public function testLoginSetsSessionCookie(): void
    {
        $client = $this->client();
        $response = $client->post('/backend/api/admin-auth/login.php', [
            'email' => DbSeeder::ADMIN_EMAIL,
            'password' => DbSeeder::ADMIN_PASSWORD,
        ]);

        $this->assertSnapshot('admin.login.ok', $response);
        $cookie = implode(' ', $response->cookies());
        self::assertStringContainsString('ARCHIMEUBLE_SESSID=', $cookie);
        self::assertStringContainsString('path=/', strtolower($cookie));
        self::assertStringContainsString('httponly', strtolower($cookie));
        self::assertStringContainsString('samesite=lax', strtolower($cookie));
        self::assertStringNotContainsString('secure', strtolower($cookie));
        $this->assertSnapshot('admin.session.ok', $client->get('/backend/api/admin-auth/session.php'));
        $this->assertSnapshot('admin.session.api-form', $client->get('/api/admin/session'));
        $this->assertSnapshot('admin.logout', $client->post('/backend/api/admin-auth/logout.php'));
        $this->assertSnapshot('admin.session.none', $client->get('/backend/api/admin-auth/session.php'));
    }

    public function testSessionWithoutLogin(): void
    {
        $this->assertSnapshot('admin.session.none', $this->client()->get('/backend/api/admin-auth/session.php'));
        $this->assertSnapshot('admin.session.api-form.none', $this->client()->get('/api/admin/session'));
    }

    public function testApiFormLoginAndLogout(): void
    {
        $client = $this->client();
        $this->assertSnapshot('admin.login.api-form', $client->post('/api/admin/login', [
            'email' => DbSeeder::ADMIN_EMAIL,
            'password' => DbSeeder::ADMIN_PASSWORD,
        ]));
        $this->assertSnapshot('admin.session.api-form', $client->get('/api/admin/session'));
        $this->assertSnapshot('admin.logout.api-form', $client->post('/api/admin/logout'));
        $this->assertSnapshot('admin.session.api-form.none', $client->get('/api/admin/session'));
    }

    public function testCustomerSessionIsNotAdmin(): void
    {
        $this->assertSnapshot('admin.session.none', $this->customer()->get('/backend/api/admin-auth/session.php'));
    }

    public function testAdminUsersList(): void
    {
        $this->assertSnapshot('admin.users.list', $this->admin()->get('/backend/api/admin-users.php'));
        $this->assertSnapshot('admin.users.unauthorized', $this->client()->get('/backend/api/admin-users.php'));
    }
}
