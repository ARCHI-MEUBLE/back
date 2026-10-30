<?php

declare(strict_types=1);

namespace Tests\Contract\Payment;

use Tests\Support\ContractTestCase;
use Tests\Support\Orders;

final class PaymentTest extends ContractTestCase
{
    public function testStripeIntentGuards(): void
    {
        $this->assertSnapshot('stripe.intent.unauthorized', $this->client()->post('/backend/api/stripe/create-payment-intent.php', ['amount' => 10, 'currency' => 'eur']));
        $this->assertSnapshot('stripe.intent.unconfigured', $this->customer()->post('/backend/api/stripe/create-payment-intent.php', ['amount' => 10, 'currency' => 'eur']));
        $this->assertSnapshot('stripe.webhook.unsigned', $this->client()->raw('POST', '/backend/api/stripe/webhook.php', '{}', ['Content-Type: application/json']));
    }

    public function testOrderPaymentEndpoints(): void
    {
        $customer = $this->customer();
        $orderId = $this->createOrder();
        $this->assertSnapshot('orders.payment-intent.unauthorized', $this->client()->post('/backend/api/orders/payment-intent.php?id=' . $orderId, ['payment_intent_id' => 'pi_test']));
        $this->assertSnapshot('orders.payment-intent', $customer->post('/backend/api/orders/payment-intent.php?id=' . $orderId, ['payment_intent_id' => 'pi_test_' . $orderId]));
        $this->assertSnapshot('orders.payment-intent.missing-order', $customer->post('/backend/api/orders/payment-intent.php?id=999999', ['payment_intent_id' => 'pi_test']));
        $this->assertSnapshot('orders.payment-confirmed.unauthorized', $this->client()->post('/backend/api/orders/payment-confirmed.php?id=' . $orderId, ['payment_intent_id' => 'pi_test', 'payment_status' => 'paid']));
        $this->assertSnapshot('orders.payment-confirmed', $customer->post('/backend/api/orders/payment-confirmed.php?id=' . $orderId, ['payment_intent_id' => 'pi_test_' . $orderId, 'payment_status' => 'paid']));
    }

    public function testPaymentLinks(): void
    {
        $admin = $this->admin();
        $orderId = $this->createOrder();
        $this->assertSnapshot('payment-links.generate.unauthorized', $this->client()->post('/backend/api/admin/generate-payment-link.php', ['order_id' => $orderId]));
        $this->assertSnapshot('payment-links.generate.invalid', $admin->post('/backend/api/admin/generate-payment-link.php', []));
        $generated = $admin->post('/backend/api/admin/generate-payment-link.php', ['order_id' => $orderId, 'expiry_days' => 7, 'payment_type' => 'full']);
        $this->assertSnapshot('payment-links.generate', $generated);
        $list = $admin->get('/backend/api/admin/payment-links.php?order_id=' . $orderId);
        $this->assertSnapshot('payment-links.list', $list);
        $links = $list->data()['links'];
        self::assertNotEmpty($links);
        $token = $links[0]['token'] ?? basename((string) $links[0]['url']);
        $this->assertSnapshot('payment-links.public', $this->client()->get('/backend/api/payment-link/index.php?token=' . $token));
        $this->assertSnapshot('payment-links.public.missing-token', $this->client()->get('/backend/api/payment-link/index.php'));
        $this->assertSnapshot('payment-links.public.unknown', $this->client()->get('/backend/api/payment-link/index.php?token=unknown-token'));
        $this->assertSnapshot('payment-links.intent.unconfigured', $this->client()->post('/backend/api/payment-link/create-payment-intent.php', ['token' => $token]));
        $this->assertSnapshot('payment-links.intent.invalid', $this->client()->post('/backend/api/payment-link/create-payment-intent.php', ['token' => 'unknown-token']));
        $this->assertSnapshot('payment-links.verify.unconfigured', $this->client()->post('/backend/api/payment-link/verify-payment.php', ['payment_intent_id' => 'pi_x']));
        $this->assertSnapshot('payment-links.verify.invalid', $this->client()->post('/backend/api/payment-link/verify-payment.php', []));
        $this->assertSnapshot('payment-links.invoice.unknown', $this->client()->get('/backend/api/payment-link/download-invoice.php?payment_intent_id=pi_unknown'));
        $this->assertSnapshot('payment-links.revoke', $admin->post('/backend/api/admin/payment-links.php', ['action' => 'revoke', 'link_id' => $links[0]['id']]));
        $this->assertSnapshot('payment-links.public.revoked', $this->client()->get('/backend/api/payment-link/index.php?token=' . $token));
        $this->assertSnapshot('payment-links.list.unauthorized', $this->client()->get('/backend/api/admin/payment-links.php?order_id=' . $orderId));
    }

    private function createOrder(): int
    {
        return Orders::createOrder($this->customer(), $this->admin(), 'paiement-' . bin2hex(random_bytes(3)));
    }
}
