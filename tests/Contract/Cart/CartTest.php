<?php

declare(strict_types=1);

namespace Tests\Contract\Cart;

use Tests\Support\ContractTestCase;

final class CartTest extends ContractTestCase
{
    public function testAllCartsRequireCustomer(): void
    {
        $this->assertSnapshot('cart.unauthorized', $this->client()->get('/backend/api/cart/index.php'));
        $this->assertSnapshot('cart.samples.unauthorized', $this->client()->get('/backend/api/cart/samples.php'));
        $this->assertSnapshot('cart.catalogue.unauthorized', $this->client()->get('/backend/api/cart/catalogue.php'));
        $this->assertSnapshot('cart.facades.unauthorized', $this->client()->get('/backend/api/cart/facades.php'));
    }

    public function testConfigurationCart(): void
    {
        $customer = $this->customer();
        $customer->delete('/backend/api/cart/index.php');
        $this->assertSnapshot('cart.empty', $customer->get('/backend/api/cart/index.php'));
        $this->assertSnapshot('cart.add', $customer->post('/backend/api/cart/index.php', ['configuration_id' => 1, 'quantity' => 1]));
        $this->assertSnapshot('cart.add.not-validated', $customer->post('/backend/api/cart/index.php', ['configuration_id' => 2, 'quantity' => 1]));
        $this->assertSnapshot('cart.add.invalid', $customer->post('/backend/api/cart/index.php', []));
        $list = $customer->get('/backend/api/cart/index.php');
        $this->assertSnapshot('cart.list', $list);
        self::assertSame(1, $list->data()['item_count']);
        $this->assertSnapshot('cart.update', $customer->put('/backend/api/cart/index.php', ['configuration_id' => 1, 'quantity' => 3]));
        self::assertSame(3, $customer->get('/backend/api/cart/index.php')->data()['item_count']);
        $this->assertSnapshot('cart.remove', $customer->delete('/backend/api/cart/index.php?configuration_id=1'));
        $this->assertSnapshot('cart.clear', $customer->delete('/backend/api/cart/index.php'));
    }

    public function testSampleCart(): void
    {
        $customer = $this->customer();
        $this->assertSnapshot('cart.samples.add', $customer->post('/backend/api/cart/samples.php', ['sample_color_id' => 1, 'quantity' => 2]));
        $this->assertSnapshot('cart.samples.add.invalid', $customer->post('/backend/api/cart/samples.php', []));
        $list = $customer->get('/backend/api/cart/samples.php');
        $this->assertSnapshot('cart.samples.list', $list);
        $itemId = $list->data()['items'][0]['id'];
        $this->assertSnapshot('cart.samples.update', $customer->put('/backend/api/cart/samples.php', ['item_id' => $itemId, 'quantity' => 1]));
        $this->assertSnapshot('cart.samples.remove', $customer->delete('/backend/api/cart/samples.php', ['item_id' => $itemId]));
        $this->assertSnapshot('cart.samples.remove.missing', $customer->delete('/backend/api/cart/samples.php', ['item_id' => $itemId]));
        $this->assertSnapshot('cart.samples.empty', $customer->get('/backend/api/cart/samples.php'));
    }

    public function testCatalogueCart(): void
    {
        $customer = $this->customer();
        $this->assertSnapshot('cart.catalogue.add', $customer->post('/backend/api/cart/catalogue.php', ['catalogue_item_id' => 1, 'variation_id' => 1, 'quantity' => 2]));
        $this->assertSnapshot('cart.catalogue.add.invalid', $customer->post('/backend/api/cart/catalogue.php', []));
        $list = $customer->get('/backend/api/cart/catalogue.php');
        $this->assertSnapshot('cart.catalogue.list', $list);
        $itemId = $list->data()['items'][0]['id'];
        $this->assertSnapshot('cart.catalogue.update', $customer->put('/backend/api/cart/catalogue.php', ['id' => $itemId, 'quantity' => 5]));
        $this->assertSnapshot('cart.catalogue.remove', $customer->delete('/backend/api/cart/catalogue.php?id=' . $itemId));
        $this->assertSnapshot('cart.catalogue.empty', $customer->get('/backend/api/cart/catalogue.php'));
    }

    public function testFacadeCart(): void
    {
        $customer = $this->customer();
        $config = ['width' => 600, 'height' => 800, 'depth' => 19, 'material' => ['id' => 1, 'name' => 'Chêne brun', 'color_hex' => '#8B5A2B', 'texture_url' => '/textures/chene_brun.png'], 'hinges' => ['type' => 'standard', 'count' => 2, 'direction' => 'left'], 'drillings' => []];
        $this->assertSnapshot('cart.facades.add', $customer->post('/backend/api/cart/facades.php', ['config' => $config, 'price' => 87.5, 'quantity' => 1]));
        $this->assertSnapshot('cart.facades.add.invalid', $customer->post('/backend/api/cart/facades.php', ['quantity' => 1]));
        $list = $customer->get('/backend/api/cart/facades.php');
        $this->assertSnapshot('cart.facades.list', $list);
        $itemId = $list->data()['items'][0]['id'];
        $this->assertSnapshot('cart.facades.remove', $customer->delete('/backend/api/cart/facades.php?id=' . $itemId));
        $this->assertSnapshot('cart.facades.empty', $customer->get('/backend/api/cart/facades.php'));
    }
}
