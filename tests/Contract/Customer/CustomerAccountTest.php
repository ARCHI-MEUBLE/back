<?php

declare(strict_types=1);

namespace Tests\Contract\Customer;

use Tests\Support\Accounts;
use Tests\Support\ContractTestCase;

final class CustomerAccountTest extends ContractTestCase
{
    public function testRegisterVerifyAndLogin(): void
    {
        $client = $this->client();
        $email = Accounts::uniqueEmail('inscrit');
        $register = $client->post('/backend/api/customers/register.php', [
            'email' => $email,
            'password' => 'Inscrit#2026',
            'first_name' => 'Ines',
            'last_name' => 'Crite',
            'phone' => '0612345678',
            'address' => '2 rue Neuve',
            'city' => 'Lille',
            'postal_code' => '59000',
            'country' => 'France',
        ]);
        $this->assertSnapshot('customer.register.ok', $register);
        self::assertTrue($register->data()['requiresVerification']);
        $this->assertSnapshot('customer.login.unverified', $client->post('/backend/api/customers/login.php', ['email' => $email, 'password' => 'Inscrit#2026']));
        $this->assertSnapshot('customer.resend-code.pending', $client->post('/backend/api/customers/resend-code.php', ['email' => $email]));
        $this->assertSnapshot('customer.register.invalid-phone', $client->post('/backend/api/customers/register.php', [
            'email' => Accounts::uniqueEmail('tel'),
            'password' => 'Inscrit#2026',
            'first_name' => 'A',
            'last_name' => 'B',
            'phone' => '12',
            'address' => 'x',
            'city' => 'y',
            'postal_code' => '59000',
            'country' => 'France',
        ]));
    }

    public function testUpdateProfile(): void
    {
        $this->assertSnapshot('customer.update.unauthorized', $this->client()->put('/backend/api/customers/update.php', ['first_name' => 'X']));
        $client = Accounts::registerVerifiedCustomer($this->client(), Accounts::uniqueEmail('profil'), 'Profil#2026');
        $this->assertSnapshot('customer.update', $client->put('/backend/api/customers/update.php', ['first_name' => 'Nouvelle', 'last_name' => 'Cliente', 'email' => Accounts::uniqueEmail('profil2'), 'phone' => '0611111111']));
        $session = $client->get('/backend/api/customers/session.php');
        self::assertSame('Nouvelle', $session->data()['customer']['first_name']);
    }

    public function testProfileEndpointUsedByTheFront(): void
    {
        $client = Accounts::registerVerifiedCustomer($this->client(), Accounts::uniqueEmail('profile'), 'Profile#2026');
        $response = $client->put('/backend/api/customers/profile.php', ['first_name' => 'Via', 'last_name' => 'Profile', 'city' => 'Roubaix']);

        $this->assertSnapshot('customer.profile', $response);
        self::assertSame('Via', $response->data()['customer']['first_name']);
    }

    public function testChangePassword(): void
    {
        $this->assertSnapshot('customer.change-password.unauthorized', $this->client()->put('/backend/api/customers/change-password.php', ['current_password' => 'a', 'new_password' => 'b']));
        $email = Accounts::uniqueEmail('mdp');
        $client = Accounts::registerVerifiedCustomer($this->client(), $email, 'Ancien#2026');
        $this->assertSnapshot('customer.change-password.wrong', $client->put('/backend/api/customers/change-password.php', ['current_password' => 'faux', 'new_password' => 'Nouveau#2026']));
        $this->assertSnapshot('customer.change-password.ok', $client->put('/backend/api/customers/change-password.php', ['current_password' => 'Ancien#2026', 'new_password' => 'Nouveau#2026']));
        self::assertSame(200, $this->client()->post('/backend/api/customers/login.php', ['email' => $email, 'password' => 'Nouveau#2026'])->status);
    }

    public function testAccountPassword(): void
    {
        $this->assertSnapshot('account.password.unauthorized', $this->client()->put('/backend/api/account/password.php', ['currentPassword' => 'a', 'newPassword' => 'b']));
        $email = Accounts::uniqueEmail('acc');
        $client = Accounts::registerVerifiedCustomer($this->client(), $email, 'Ancien#2026');
        $this->assertSnapshot('account.password.wrong', $client->put('/backend/api/account/password.php', ['currentPassword' => 'faux', 'newPassword' => 'Nouveau#2026']));
        $this->assertSnapshot('account.password.ok', $client->put('/backend/api/account/password.php', ['currentPassword' => 'Ancien#2026', 'newPassword' => 'Nouveau#2026']));
    }

    public function testAccountDelete(): void
    {
        $this->assertSnapshot('account.delete.unauthorized', $this->client()->delete('/backend/api/account/delete.php', ['password' => 'x']));
        $client = Accounts::registerVerifiedCustomer($this->client(), Accounts::uniqueEmail('suppr'), 'Suppr#2026');
        $this->assertSnapshot('account.delete.wrong-password', $client->delete('/backend/api/account/delete.php', ['password' => 'faux']));
        $this->assertSnapshot('account.delete.ok', $client->delete('/backend/api/account/delete.php', ['password' => 'Suppr#2026']));
        $this->assertSnapshot('account.delete.session-after', $client->get('/backend/api/customers/session.php'));
    }

    public function testCustomerDelete(): void
    {
        $this->assertSnapshot('customer.delete.unauthorized', $this->client()->delete('/backend/api/customers/delete.php'));
        $client = Accounts::registerVerifiedCustomer($this->client(), Accounts::uniqueEmail('del'), 'Del#2026');
        $this->assertSnapshot('customer.delete.ok', $client->delete('/backend/api/customers/delete.php'));
    }
}
