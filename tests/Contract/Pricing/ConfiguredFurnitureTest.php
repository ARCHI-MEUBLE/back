<?php

declare(strict_types=1);

namespace Tests\Contract\Pricing;

use Tests\Support\ContractTestCase;

final class ConfiguredFurnitureTest extends ContractTestCase
{
    private const SAMPLES = '/backend/api/admin/samples.php';

    public function testHandlesAndColourPricesAddUpInTheSameEstimate(): void
    {
        $models = array_column($this->client()->get('/backend/api/handle-models.php')->data()['data'], 'id', 'name');
        $leather = $models['SAFARI cuir marron clair, entraxe 160'];
        $wire = $models['Fil Ø10 aluminium anodisé, entraxe 128'];
        $colour = $this->admin()->get(self::SAMPLES)->data()['data'][0]['colors'][0]['id'];

        $mixed = $this->lines($colour, $leather, $wire);
        $wireOnly = $this->lines($colour, $wire, $wire);
        self::assertGreaterThan($wireOnly['hardware'], $mixed['hardware']);
        self::assertSame($wireOnly['panels'], $mixed['panels']);

        $this->admin()->post(self::SAMPLES, ['action' => 'update_color', 'id' => $colour, 'furniture_price_per_m2' => 900]);
        try {
            $dearColour = $this->lines($colour, $leather, $wire);
            self::assertGreaterThan($mixed['panels'], $dearColour['panels']);
            self::assertGreaterThan($mixed['fronts'], $dearColour['fronts']);
            self::assertSame($mixed['hardware'], $dearColour['hardware']);
        } finally {
            $this->admin()->post(self::SAMPLES, ['action' => 'update_color', 'id' => $colour, 'furniture_price_per_m2' => null]);
        }
        self::assertSame($mixed, $this->lines($colour, $leather, $wire));
    }

    private function lines(int $colorId, int $furnitureHandle, int $secondDoorHandle): array
    {
        $response = $this->client()->post('/backend/api/pricing/estimate.php', ['config_data' => [
            'dimensions' => ['width' => 1000, 'height' => 800, 'depth' => 400],
            'styling' => ['colorId' => $colorId],
            'features' => ['doorType' => 'none'],
            'handles' => ['modelId' => $furnitureHandle, 'doorOrientation' => 'vertical', 'drawerOrientation' => 'horizontal'],
            'advancedZones' => ['id' => 'root', 'type' => 'vertical', 'children' => [
                ['id' => 'a', 'type' => 'leaf', 'content' => 'door'],
                ['id' => 'b', 'type' => 'leaf', 'content' => 'door', 'handleModelId' => $secondDoorHandle],
            ]],
        ]]);
        self::assertSame(200, $response->status, $response->body);
        return array_column($response->data()['data']['lines'], 'amount', 'key');
    }
}
