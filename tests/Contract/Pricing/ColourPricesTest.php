<?php

declare(strict_types=1);

namespace Tests\Contract\Pricing;

use Tests\Support\ContractTestCase;

final class ColourPricesTest extends ContractTestCase
{
    private const SAMPLES = '/backend/api/admin/samples.php';
    private const MATERIALS = '/backend/api/admin/material-prices.php';

    public function testEachColourCanHaveItsOwnPricePerSquareMetre(): void
    {
        $admin = $this->admin();
        $type = $admin->get(self::SAMPLES)->data()['data'][0];
        $dearColour = $type['colors'][0]['id'];
        $sameWoodColour = $this->createColour($type['id'], '#123456');

        try {
            $materialPrice = $this->estimate($dearColour);
            self::assertSame($materialPrice, $this->estimate($sameWoodColour));

            $admin->post(self::SAMPLES, ['action' => 'update_color', 'id' => $dearColour, 'furniture_price_per_m2' => 900]);
            self::assertGreaterThan($materialPrice, $this->estimate($dearColour));
            self::assertSame($materialPrice, $this->estimate($sameWoodColour));

            $admin->post(self::SAMPLES, ['action' => 'update_color', 'id' => $dearColour, 'furniture_price_per_m2' => null]);
            self::assertSame($materialPrice, $this->estimate($dearColour));
        } finally {
            $admin->post(self::SAMPLES, ['action' => 'update_color', 'id' => $dearColour, 'furniture_price_per_m2' => null]);
            $admin->post(self::SAMPLES, ['action' => 'delete_color', 'id' => $sameWoodColour]);
        }
    }

    public function testAMaterialPriceChangeReachesOnlyTheColoursWithoutTheirOwnPrice(): void
    {
        $admin = $this->admin();
        $type = $admin->get(self::SAMPLES)->data()['data'][0];
        $ownPriceColour = $type['colors'][0]['id'];
        $plainColour = $this->createColour($type['id'], '#654321');
        $material = null;
        foreach ($admin->get(self::MATERIALS)->data()['data']['materials'] as $row) {
            if (strtolower($row['material']) === strtolower(trim($type['material']))) {
                $material = $row;
            }
        }
        self::assertNotNull($material, 'Material ' . $type['material'] . ' missing from the material prices');
        self::assertNotNull($material['price_per_m2'], 'Material ' . $type['material'] . ' has no price to restore');

        $admin->post(self::SAMPLES, ['action' => 'update_color', 'id' => $ownPriceColour, 'furniture_price_per_m2' => 300]);
        try {
            $ownPrice = $this->estimate($ownPriceColour);
            $plainBefore = $this->estimate($plainColour);
            $admin->post(self::MATERIALS, ['action' => 'set', 'material' => $material['material'], 'price_per_m2' => 1500]);
            self::assertGreaterThan($plainBefore, $this->estimate($plainColour));
            self::assertSame($ownPrice, $this->estimate($ownPriceColour));
        } finally {
            $admin->post(self::MATERIALS, ['action' => 'set', 'material' => $material['material'], 'price_per_m2' => (float) $material['price_per_m2']]);
            $admin->post(self::SAMPLES, ['action' => 'update_color', 'id' => $ownPriceColour, 'furniture_price_per_m2' => null]);
            $admin->post(self::SAMPLES, ['action' => 'delete_color', 'id' => $plainColour]);
        }
    }

    private function createColour(int $typeId, string $hex): int
    {
        $created = $this->admin()->post(self::SAMPLES, ['action' => 'create_color', 'type_id' => $typeId, 'name' => 'Teinte ' . $hex, 'hex' => $hex]);
        $id = (int) ($created->data()['id'] ?? 0);
        self::assertGreaterThan(0, $id, $created->body);
        return $id;
    }

    private function estimate(int $colorId): int
    {
        return $this->client()->post('/backend/api/pricing/estimate.php', ['config_data' => [
            'dimensions' => ['width' => 1000, 'height' => 800, 'depth' => 400],
            'styling' => ['colorId' => $colorId],
            'features' => ['doorType' => 'none'],
        ]])->data()['data']['price'];
    }
}
