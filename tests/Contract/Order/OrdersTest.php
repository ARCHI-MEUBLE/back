<?php

declare(strict_types=1);

namespace Tests\Contract\Order;

use Tests\Support\ApiClient;
use Tests\Support\ContractTestCase;
use Tests\Support\Orders;

final class OrdersTest extends ContractTestCase
{
    public function testOrderLifecycle(): void
    {
        $customer = $this->customer();
        $this->assertSnapshot('orders.create.unauthorized', $this->client()->post('/backend/api/orders/create.php', ['shipping_address' => 'x']));
        $this->assertSnapshot('orders.create.empty-cart', $customer->post('/backend/api/orders/create.php', ['shipping_address' => '1 rue des Tests, 59000 Lille, France']));
        $this->fillCart($customer);
        $this->assertSnapshot('orders.create.invalid', $customer->post('/backend/api/orders/create.php', ['notes' => 'sans adresse']));
        $created = $customer->post('/backend/api/orders/create.php', [
            'shipping_address' => '1 rue des Tests, 59000 Lille, France',
            'billing_address' => '1 rue des Tests, 59000 Lille, France',
            'payment_method' => 'stripe',
            'notes' => 'Livrer le matin',
        ]);
        $this->assertSnapshot('orders.create', $created);
        $order = $created->data()['order'];
        self::assertArrayHasKey('needs_validation', $order);
        $orderId = (int) $order['id'];
        self::assertSame(0, $customer->get('/backend/api/cart/index.php')->data()['item_count']);
        $this->assertSnapshot('orders.list', $customer->get('/backend/api/orders/list.php'));
        $this->assertSnapshot('orders.list.paged', $customer->get('/backend/api/orders/list.php?limit=5&offset=0'));
        $one = $customer->get('/backend/api/orders/list.php?id=' . $orderId);
        $this->assertSnapshot('orders.one', $one);
        foreach (['payment_strategy', 'deposit_amount', 'remaining_amount', 'deposit_payment_status', 'balance_payment_status', 'items', 'samples'] as $key) {
            self::assertArrayHasKey($key, $one->data()['order'], $key);
        }
        $this->assertSnapshot('orders.one.missing', $customer->get('/backend/api/orders/list.php?id=999999'));
        $this->assertSnapshot('orders.send-confirmation', $customer->post('/backend/api/orders/send-confirmation.php', ['order_id' => $orderId]));
        $this->assertSnapshot('orders.validate.paid-order', $customer->post('/backend/api/orders/validate.php', ['order_id' => $orderId, 'payment_method' => 'free_samples']));
        $this->assertSnapshot('orders.validate.missing', $customer->post('/backend/api/orders/validate.php', ['order_id' => 999999]));
        $this->adminSide($orderId);
        $this->assertSnapshot('orders.invoice.customer', $customer->get('/backend/api/orders/invoice.php?id=' . $orderId . '&json=1'));
        $this->assertSnapshot('orders.delete.unauthorized', $this->client()->post('/backend/api/orders/delete.php', ['order_id' => $orderId]));
        $this->assertSnapshot('orders.delete', $customer->post('/backend/api/orders/delete.php', ['order_id' => $orderId]));
    }

    private function adminSide(int $orderId): void
    {
        $admin = $this->admin();
        $this->assertSnapshot('orders.admin.unauthorized', $this->client()->get('/backend/api/admin/orders.php'));
        $list = $admin->get('/backend/api/admin/orders.php');
        $this->assertSnapshot('orders.admin.list', $list);
        self::assertNotEmpty($list->data()['orders']);
        $this->assertSnapshot('orders.admin.list.status', $admin->get('/backend/api/admin/orders.php?status=pending'));
        $one = $admin->get('/backend/api/admin/orders.php?id=' . $orderId);
        $this->assertSnapshot('orders.admin.one', $one);
        self::assertArrayHasKey('customer', $one->data()['order']);
        $this->assertSnapshot('orders.admin.one.invalid', $admin->get('/backend/api/admin/orders.php?id=abc'));
        $this->assertSnapshot('orders.admin.status', $admin->put('/backend/api/admin/orders.php', ['order_id' => $orderId, 'status' => 'confirmed']));
        $this->assertSnapshot('orders.admin.status.invalid', $admin->put('/backend/api/admin/orders.php', ['order_id' => $orderId, 'status' => 'nope']));
        $this->assertSnapshot('orders.admin.payment-strategy', $admin->post('/backend/api/admin/orders/payment-strategy.php', ['order_id' => $orderId, 'strategy' => 'deposit', 'deposit_percentage' => 30]));
        $this->assertSnapshot('orders.admin.payment-strategy.invalid', $admin->post('/backend/api/admin/orders/payment-strategy.php', ['order_id' => $orderId]));
        $this->assertSnapshot('orders.admin.analytics', $admin->get('/backend/api/admin/payment-analytics.php?period=month'));
        $this->assertSnapshot('orders.admin.transactions', $admin->get('/backend/api/admin/recent-transactions.php?period=month&limit=5'));
        $csv = $admin->get('/backend/api/admin/export-payments.php?period=month');
        self::assertSame(200, $csv->status);
        self::assertStringStartsWith('text/csv', $csv->contentType());
        $this->assertSnapshot('orders.admin.sync-payment', $admin->post('/backend/api/admin/sync-payment-status.php', ['order_id' => $orderId]));
        $this->assertSnapshot('orders.admin.invoice', $admin->get('/backend/api/orders/invoice.php?id=' . $orderId . '&json=1'));
        $dxf = $admin->get('/backend/api/files/dxf.php?id=1');
        self::assertSame(200, $dxf->status, $dxf->body);
        self::assertStringContainsString('dxf', $dxf->contentType());
        $this->assertSnapshot('files.dxf.unauthorized', $this->client()->get('/backend/api/files/dxf.php?id=1'));
        $this->assertSnapshot('files.dxf.missing', $admin->get('/backend/api/files/dxf.php?id=999999'));
    }

    private function fillCart(ApiClient $customer): void
    {
        $configurationId = Orders::validatedConfigurationId($customer, $this->admin(), 'commande-' . bin2hex(random_bytes(3)));
        $responses = [
            $customer->post('/backend/api/cart/index.php', ['configuration_id' => $configurationId, 'quantity' => 1]),
            $customer->post('/backend/api/cart/samples.php', ['sample_color_id' => 1, 'quantity' => 1]),
            $customer->post('/backend/api/cart/catalogue.php', ['catalogue_item_id' => 1, 'variation_id' => 1, 'quantity' => 2]),
            $customer->post('/backend/api/cart/facades.php', ['config' => ['width' => 600, 'height' => 800, 'depth' => 19, 'material' => ['id' => 1], 'hinges' => ['type' => 'standard', 'count' => 2], 'drillings' => []], 'price' => 87.5, 'quantity' => 1]),
        ];
        foreach ($responses as $response) {
            self::assertContains($response->status, [200, 201], $response->body);
        }
    }
}
