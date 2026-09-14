<?php

declare(strict_types=1);

namespace Tests\Contract\Customer;

use Tests\Support\ContractTestCase;
use Tests\Support\DbSeeder;

final class CustomerAuthTest extends ContractTestCase
{
    public function testLoginValidation(): void
    {
        $this->assertSnapshot('customer.login.missing', $this->client()->post('/backend/api/customers/login.php', ['email' => 'x']));
    }

    public function testLoginInvalid(): void
    {
        $response = $this->client()->post('/backend/api/customers/login.php', [
            'email' => DbSeeder::CUSTOMER_EMAIL,
            'password' => 'wrong',
        ]);

        $this->assertSnapshot('customer.login.invalid', $response);
    }

    public function testLoginSessionLogout(): void
    {
        $client = $this->client();
        $login = $client->post('/backend/api/customers/login.php', [
            'email' => DbSeeder::CUSTOMER_EMAIL,
            'password' => DbSeeder::CUSTOMER_PASSWORD,
        ]);

        $this->assertSnapshot('customer.login.ok', $login);
        self::assertStringContainsString('ARCHIMEUBLE_SESSID=', implode(' ', $login->cookies()));
        $session = $client->get('/backend/api/customers/session.php');
        $this->assertSnapshot('customer.session.ok', $session);
        self::assertTrue($session->data()['authenticated']);
        self::assertSame(DbSeeder::CUSTOMER_EMAIL, $session->data()['customer']['email']);
        $this->assertSnapshot('customer.session.logout', $client->delete('/backend/api/customers/session.php'));
        $this->assertSnapshot('customer.session.none', $client->get('/backend/api/customers/session.php'));
    }

    public function testSessionAnonymous(): void
    {
        $this->assertSnapshot('customer.session.none', $this->client()->get('/backend/api/customers/session.php'));
    }

    public function testAdminSessionIsNotCustomer(): void
    {
        $this->assertSnapshot('customer.session.none', $this->admin()->get('/backend/api/customers/session.php'));
    }

    public function testRegisterValidation(): void
    {
        $this->assertSnapshot('customer.register.missing', $this->client()->post('/backend/api/customers/register.php', ['email' => 'new@test.archimeuble.com']));
    }

    public function testRegisterExistingEmail(): void
    {
        $response = $this->client()->post('/backend/api/customers/register.php', [
            'email' => DbSeeder::CUSTOMER_EMAIL,
            'password' => 'Whatever#2026',
            'first_name' => 'Claire',
            'last_name' => 'Test',
        ]);

        $this->assertSnapshot('customer.register.duplicate', $response);
    }

    public function testForgotPasswordUnknownEmail(): void
    {
        $this->assertSnapshot('customer.forgot-password', $this->client()->post('/backend/api/customers/forgot-password.php', ['email' => 'nobody@test.archimeuble.com']));
    }

    public function testResetPasswordInvalidToken(): void
    {
        $this->assertSnapshot('customer.reset-password.invalid', $this->client()->post('/backend/api/customers/reset-password.php', ['token' => 'nope', 'password' => 'NewPass#2026']));
    }

    public function testVerifyEmailInvalidCode(): void
    {
        $response = $this->client()->post('/backend/api/customers/verify-email.php', ['email' => DbSeeder::CUSTOMER_EMAIL, 'code' => '000000']);

        $this->assertJsonResponse($response);
        $this->assertSnapshot('customer.verify-email.invalid', $response);
    }

    public function testResendCode(): void
    {
        $response = $this->client()->post('/backend/api/customers/resend-code.php', ['email' => DbSeeder::CUSTOMER_EMAIL]);

        $this->assertJsonResponse($response);
        $this->assertSnapshot('customer.resend-code', $response);
    }
}
