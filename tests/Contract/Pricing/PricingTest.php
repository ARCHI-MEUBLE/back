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

    public function testFurnitureEstimateIsPublicAndReturnsDeliverySeparately(): void
    {
        $response = $this->client()->post('/backend/api/pricing/estimate.php', [
            'config_data' => [
                'dimensions' => ['width' => 1000, 'height' => 800, 'depth' => 400],
                'styling' => ['materialKey' => 'mdf_melamine'],
                'features' => ['doorType' => 'none'],
            ],
        ]);
        self::assertSame(200, $response->status, $response->body);
        $data = $response->data()['data'];

        self::assertArrayHasKey('delivery', $data);
        self::assertIsInt($data['delivery']);
        self::assertNotContains('delivery', array_column($data['lines'], 'key'));

        $invalid = $this->client()->post('/backend/api/pricing/estimate.php', [
            'config_data' => ['dimensions' => ['width' => 100, 'height' => 800, 'depth' => 400]],
        ]);
        self::assertSame(400, $invalid->status);
    }

    public function testWritesRequireAdmin(): void
    {
        $this->assertSnapshot('pricing-config.list.unauthorized', $this->client()->get('/backend/api/pricing-config/index.php'));
        $this->assertSnapshot('pricing-config.create.unauthorized', $this->client()->post('/backend/api/pricing-config/index.php', ['category' => 'a', 'item_type' => 'b', 'param_name' => 'c', 'param_value' => 1, 'unit' => 'eur']));
    }

    public function testPricingConfig(): void
    {
        $admin = $this->admin();
        $list = $admin->get('/backend/api/pricing-config/index.php');
        $this->assertSnapshot('pricing-config.list', $list);
        $rows = $list->data()['data'];
        self::assertNotEmpty($rows);
        self::assertContains('materials', array_unique(array_column($rows, 'category')));
        $this->assertSnapshot('pricing-config.all', $admin->get('/backend/api/pricing-config/index.php?active_only=false'));
        $depositPercent = null;
        foreach ($rows as $row) {
            if ($row['category'] === 'payment' && $row['item_type'] === 'deposit' && $row['param_name'] === 'default_percent') {
                $depositPercent = $row;
                break;
            }
        }
        self::assertIsArray($depositPercent);
        $this->assertSnapshot('pricing-config.update', $admin->put('/backend/api/pricing-config/index.php', ['id' => $depositPercent['id'], 'param_value' => 51]));
        $this->assertSnapshot('pricing-config.update.invalid', $admin->put('/backend/api/pricing-config/index.php', ['param_value' => 51]));
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
