<?php

declare(strict_types=1);

namespace Tests\Contract\Pricing;

use Tests\Support\ContractTestCase;

final class PricingTest extends ContractTestCase
{
    public function testPricingList(): void
    {
        $response = $this->client()->get('/backend/api/pricing/index.php');

        $this->assertSnapshot('pricing.list', $response);
        self::assertNotEmpty($response->data()['data']);
        $this->assertSnapshot('pricing.by-name', $this->client()->get('/backend/api/pricing/index.php?name=default'));
    }

    public function testWritesRequireAdmin(): void
    {
        $this->assertSnapshot('pricing.create.unauthorized', $this->client()->post('/backend/api/pricing/index.php', ['name' => 'x', 'price_per_m3' => 1]));
        $this->assertSnapshot('pricing-config.create.unauthorized', $this->client()->post('/backend/api/pricing-config/index.php', ['category' => 'a', 'item_type' => 'b', 'param_name' => 'c', 'param_value' => 1, 'unit' => 'eur']));
    }

    public function testPricingCrud(): void
    {
        $admin = $this->admin();
        $created = $admin->post('/backend/api/pricing/index.php', ['name' => 'test-grid', 'description' => 'Grille test', 'price_per_m3' => 1234.5]);
        $this->assertSnapshot('pricing.create', $created);
        $id = (int) ($created->data()['data']['id'] ?? $created->data()['id'] ?? 0);
        self::assertGreaterThan(0, $id, $created->body);
        $this->assertSnapshot('pricing.create.invalid', $admin->post('/backend/api/pricing/index.php', ['description' => 'sans nom']));
        $this->assertSnapshot('pricing.update', $admin->put('/backend/api/pricing/index.php', ['id' => $id, 'name' => 'test-grid', 'description' => 'Grille test 2', 'price_per_m3' => 1300, 'is_active' => true]));
        $this->assertSnapshot('pricing.delete', $admin->delete('/backend/api/pricing/index.php', ['id' => $id]));
        $this->assertSnapshot('pricing.delete.missing', $admin->delete('/backend/api/pricing/index.php', ['id' => $id]));
    }

    public function testPricingConfig(): void
    {
        $list = $this->client()->get('/backend/api/pricing-config/index.php');
        $this->assertSnapshot('pricing-config.list', $list);
        $rows = $list->data()['data'];
        self::assertNotEmpty($rows);
        self::assertContains('materials', array_unique(array_column($rows, 'category')));
        $this->assertSnapshot('pricing-config.by-category', $this->client()->get('/backend/api/pricing-config/index.php?category=doors&active_only=1'));
        $this->assertSnapshot('pricing-config.update', $this->admin()->put('/backend/api/pricing-config/index.php', ['id' => $rows[0]['id'], 'param_value' => 51]));
        $this->assertSnapshot('pricing-config.update.invalid', $this->admin()->put('/backend/api/pricing-config/index.php', ['param_value' => 51]));
    }
}
