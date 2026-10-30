<?php

declare(strict_types=1);

namespace Tests\Contract\Pricing;

use Tests\Support\ContractTestCase;

final class PricingTest extends ContractTestCase
{
    private const TV_UNIT = [
        'dimensions' => ['width' => 1700, 'height' => 600, 'depth' => 450],
        'styling' => ['materialKey' => 'mdf_melamine', 'socle' => 'none'],
        'features' => ['doorType' => 'none'],
        'advancedZones' => ['id' => 'root', 'type' => 'vertical', 'children' => [
            ['id' => 'a', 'type' => 'leaf', 'content' => 'drawer', 'handleType' => 'horizontal_bar'],
            ['id' => 'b', 'type' => 'leaf', 'content' => 'empty'],
            ['id' => 'c', 'type' => 'leaf', 'content' => 'door', 'handleType' => 'knob'],
        ]],
    ];

    public function testWritesRequireAdmin(): void
    {
        $this->assertSnapshot('pricing-config.create.unauthorized', $this->client()->post('/backend/api/pricing-config/index.php', ['category' => 'a', 'item_type' => 'b', 'param_name' => 'c', 'param_value' => 1, 'unit' => 'eur']));
    }

    public function testPricingConfig(): void
    {
        $list = $this->client()->get('/backend/api/pricing-config/index.php');
        $this->assertSnapshot('pricing-config.list', $list);
        $rows = $list->data()['data'];
        self::assertNotEmpty($rows);
        self::assertContains('materials', array_unique(array_column($rows, 'category')));
        self::assertNotContains('glass', array_column($rows, 'item_type'));
        $this->assertSnapshot('pricing-config.by-category', $this->client()->get('/backend/api/pricing-config/index.php?category=fronts&active_only=1'));
        $this->assertSnapshot('pricing-config.update', $this->admin()->put('/backend/api/pricing-config/index.php', ['id' => $rows[0]['id'], 'param_value' => 51]));
        $this->assertSnapshot('pricing-config.update.invalid', $this->admin()->put('/backend/api/pricing-config/index.php', ['param_value' => 51]));
    }

    public function testEstimate(): void
    {
        $response = $this->client()->post('/backend/api/pricing/estimate.php', ['config_data' => self::TV_UNIT]);
        $this->assertSnapshot('pricing.estimate', $response);
        $estimate = $response->data()['data'];
        self::assertGreaterThanOrEqual(1200, $estimate['price']);
        self::assertLessThanOrEqual(1500, $estimate['price']);
        self::assertLessThan($estimate['price'], $estimate['low']);
        self::assertGreaterThan($estimate['price'], $estimate['high']);
        $this->assertSnapshot('pricing.estimate.invalid', $this->client()->post('/backend/api/pricing/estimate.php', ['config_data' => ['dimensions' => ['width' => 1700]]]));
    }
}
