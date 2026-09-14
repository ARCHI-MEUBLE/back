<?php

declare(strict_types=1);

namespace Tests\Contract\Http;

use Tests\Support\ContractTestCase;
use Tests\Support\DbSeeder;

final class LegacyAuthTest extends ContractTestCase
{
    public function testSessionAnonymous(): void
    {
        $this->assertSnapshot('auth.session.none', $this->client()->get('/api/auth/session'));
    }

    public function testLoginFlow(): void
    {
        $client = $this->client();
        $this->assertSnapshot('auth.login.missing', $client->post('/api/auth/login', []));
        $this->assertSnapshot('auth.login.invalid', $client->post('/api/auth/login', ['email' => DbSeeder::USER_EMAIL, 'password' => 'wrong']));
        $this->assertSnapshot('auth.login.ok', $client->post('/api/auth/login', ['email' => DbSeeder::USER_EMAIL, 'password' => DbSeeder::USER_PASSWORD]));
        $this->assertSnapshot('auth.session.ok', $client->get('/api/auth/session'));
        $this->assertSnapshot('auth.password.invalid', $client->put('/api/auth/password', ['currentPassword' => 'wrong', 'newPassword' => 'Other#2026']));
        $this->assertSnapshot('auth.logout', $client->delete('/api/auth/logout'));
        $this->assertSnapshot('auth.session.none', $client->get('/api/auth/session'));
    }

    public function testRegister(): void
    {
        $email = 'legacy-' . bin2hex(random_bytes(3)) . '@test.archimeuble.com';
        $this->assertSnapshot('auth.register.ok', $this->client()->post('/api/auth/register', ['email' => $email, 'password' => 'Legacy#2026', 'name' => 'Nouveau']));
        $this->assertSnapshot('auth.register.duplicate', $this->client()->post('/api/auth/register', ['email' => $email, 'password' => 'Legacy#2026', 'name' => 'Nouveau']));
        $this->assertSnapshot('auth.register.missing', $this->client()->post('/api/auth/register', ['email' => $email]));
    }

    public function testPasswordReset(): void
    {
        $this->assertSnapshot('auth.forgot-password', $this->client()->post('/api/auth/forgot-password', ['email' => DbSeeder::USER_EMAIL]));
        $this->assertSnapshot('auth.reset-password.invalid', $this->client()->post('/api/auth/reset-password', ['token' => 'nope', 'password' => 'Other#2026']));
    }

    public function testUnknownAction(): void
    {
        $this->assertSnapshot('auth.unknown', $this->client()->get('/api/auth/whatever'));
    }
}
