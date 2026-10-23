<?php

declare(strict_types=1);

namespace Tests\Contract\Security;

use Tests\Support\Accounts;
use Tests\Support\ApiClient;
use Tests\Support\ContractTestCase;
use Tests\Support\DatabaseUrl;
use Tests\Support\Orders;

final class SecurityRegressionTest extends ContractTestCase
{
    public function testCustomerCannotDeclareOrderPaid(): void
    {
        $customer = $this->customer();
        $orderId = Orders::createOrder($customer, $this->admin(), 'faux-paiement-' . bin2hex(random_bytes(3)));
        $response = $customer->post('/backend/api/orders/payment-confirmed.php?id=' . $orderId, ['payment_intent_id' => 'pi_fake', 'payment_status' => 'paid']);

        self::assertSame(403, $response->status, $response->body);
        self::assertNotSame('paid', $customer->get('/backend/api/orders/list.php?id=' . $orderId)->data()['order']['payment_status'] ?? null);
    }

    public function testFacadePriceIsComputedByTheServer(): void
    {
        $customer = Accounts::registerVerifiedCustomer($this->client(), Accounts::uniqueEmail('facade'), 'Facade#2026');
        $config = ['width' => 600, 'height' => 800, 'depth' => 19, 'material' => ['id' => 1, 'price_per_m2' => 0.01], 'hinges' => ['type' => 'standard', 'count' => 2], 'drillings' => []];
        $added = $customer->post('/backend/api/cart/facades.php', ['config' => $config, 'price' => 0.01, 'quantity' => 1]);

        self::assertSame(201, $added->status, $added->body);
        $statement = DatabaseUrl::connect(DatabaseUrl::fromEnv())->prepare("SELECT setting_key, setting_value FROM facade_settings WHERE setting_key IN ('hinge_base_price', 'hinge_coefficient')");
        $statement->execute();
        $settings = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
        $base = 0.6 * 0.8 * 150 + 2 * (float) $settings['hinge_base_price'];
        $expected = round($base * (1 + 2 * (float) $settings['hinge_coefficient']), 2);
        self::assertEqualsWithDelta($expected, (float) $customer->get('/backend/api/cart/facades.php')->data()['items'][0]['unit_price'], 0.001);
    }

    public function testCartRejectsNegativeOrHugeQuantities(): void
    {
        $customer = $this->customer();

        self::assertSame(400, $customer->post('/backend/api/cart/catalogue.php', ['catalogue_item_id' => 1, 'quantity' => -5])->status);
        self::assertSame(400, $customer->post('/backend/api/cart/catalogue.php', ['catalogue_item_id' => 1, 'quantity' => 100000])->status);
        self::assertSame(400, $customer->post('/backend/api/cart/samples.php', ['sample_color_id' => 2, 'quantity' => -1])->status);
    }

    public function testReRegisteringAnUnverifiedAccountReplacesThePassword(): void
    {
        $email = Accounts::uniqueEmail('squat');
        $attacker = $this->register($this->client(), $email, 'Attaquant#2026');
        self::assertSame(201, $attacker->status, $attacker->body);
        self::assertSame(403, $this->client()->post('/backend/api/customers/login.php', ['email' => $email, 'password' => 'Attaquant#2026'])->status);

        $owner = $this->client();
        self::assertSame(200, $this->register($owner, $email, 'Proprietaire#2026')->status);
        $verify = $owner->post('/backend/api/customers/verify-email.php', ['email' => $email, 'code' => self::latestCode($email)]);
        self::assertSame(200, $verify->status, $verify->body);

        self::assertSame(401, $this->client()->post('/backend/api/customers/login.php', ['email' => $email, 'password' => 'Attaquant#2026'])->status);
        self::assertSame(200, $this->client()->post('/backend/api/customers/login.php', ['email' => strtoupper($email), 'password' => 'Proprietaire#2026'])->status);
    }

    public function testVerificationCodeGuessingIsThrottled(): void
    {
        $email = Accounts::uniqueEmail('brute');
        $this->register($this->client(), $email, 'Brute#2026xx');
        $statuses = [];
        for ($i = 0; $i < 6; $i++) {
            $statuses[] = $this->client()->post('/backend/api/customers/verify-email.php', ['email' => $email, 'code' => '000000'])->status;
        }

        self::assertSame(429, end($statuses));
    }

    public function testCalendlyConfirmationOnlyCallsTheCalendlyApi(): void
    {
        $response = $this->client()->post('/backend/api/calendly/send-confirmation.php', ['invitee_uri' => 'https://attacker.example/steal']);

        self::assertSame(400, $response->status, $response->body);
    }

    public function testConfigurationsNoLongerLeakSessions(): void
    {
        self::assertSame(401, $this->client()->get('/api/configurations')->status);
        $created = $this->customer()->post('/backend/api/configurations/save.php', ['prompt' => 'M1(1000,400,600)EFb', 'price' => 900, 'glb_url' => '/models/a.glb']);

        self::assertNull($created->data()['configuration']['user_session'] ?? null);
        self::assertSame(400, $this->customer()->post('/backend/api/configurations/save.php', ['prompt' => 'M1(1000,400,600)EFb', 'price' => -1])->status);
    }

    private function register(ApiClient $client, string $email, string $password): \Tests\Support\ApiResponse
    {
        return $client->post('/backend/api/customers/register.php', [
            'email' => $email, 'password' => $password, 'first_name' => 'Test', 'last_name' => 'Securite',
            'phone' => '0612345678', 'address' => '2 rue Neuve', 'city' => 'Lille', 'postal_code' => '59000', 'country' => 'France',
        ]);
    }

    private static function latestCode(string $email): string
    {
        $statement = DatabaseUrl::connect(DatabaseUrl::fromEnv())->prepare('SELECT code FROM email_verifications WHERE email = ? AND used = FALSE ORDER BY created_at DESC LIMIT 1');
        $statement->execute([$email]);
        return (string) $statement->fetchColumn();
    }
}
