<?php

declare(strict_types=1);

namespace Tests\Contract\Catalogue;

use Tests\Support\ContractTestCase;

final class HandleModelDimensionsTest extends ContractTestCase
{
    private const ADMIN = '/backend/api/admin/handle-models.php';
    private const MODEL = ['name' => 'Poignée cotée', 'shape' => 'bar', 'finish_hex' => '#123abc', 'length_mm' => 128, 'price_per_unit' => 18.5];

    public function testLmcModelsFollowTheManufacturerReferenceTables(): void
    {
        $byName = array_column($this->client()->get('/backend/api/handle-models.php')->data()['data'], null, 'name');
        $expected = [
            'Fil Ø10 aluminium anodisé, entraxe 128' => [138, 128, 10, 30],
            'Fil Ø10 aluminium anodisé, entraxe 192' => [202, 192, 10, 30],
            'FLAT chromé, entraxe 128' => [143, 128, 8, 34],
            'FLAT nickelé mat, entraxe 192' => [207, 192, 8, 34],
            'COMPACT noir, entraxe 160' => [172, 160, 8, 36],
            'LOGGIC laiton noir, entraxe 200' => [223, 200, 20, 40],
            'SAFARI cuir marron clair, entraxe 160' => [224, 160, 24, 25],
            'HEXXA noir mat, longueur 200' => [200, null, null, null],
            'Cuvette LIVING inox brossé' => [120, null, 40, 1],
            'Cuvette LIVING noire' => [120, null, 40, 1],
        ];
        foreach ($expected as $name => $dimensions) {
            self::assertArrayHasKey($name, $byName);
            $model = $byName[$name];
            self::assertSame($dimensions, [$model['length_mm'], $model['center_distance_mm'], $model['width_mm'], $model['projection_mm']], $name);
        }
    }

    public function testDimensionsAreKeptAndClearedForShapesWithoutLength(): void
    {
        $admin = $this->admin();
        $created = $admin->post(self::ADMIN, ['action' => 'create', 'center_distance_mm' => 96, 'width_mm' => 12, 'projection_mm' => 28] + self::MODEL);
        self::assertSame(201, $created->status, $created->body);
        $id = (int) $created->data()['id'];
        self::assertSame([128, 96, 12, 28], $this->dimensions($id));

        $admin->post(self::ADMIN, ['action' => 'update', 'id' => $id, 'name' => 'Poignée cotée renommée']);
        self::assertSame([128, 96, 12, 28], $this->dimensions($id));

        $admin->post(self::ADMIN, ['action' => 'update', 'id' => $id, 'width_mm' => null]);
        self::assertSame([128, 96, null, 28], $this->dimensions($id));

        $admin->post(self::ADMIN, ['action' => 'update', 'id' => $id, 'shape' => 'knob']);
        self::assertSame([null, null, null, 28], $this->dimensions($id));
        $admin->post(self::ADMIN, ['action' => 'delete', 'id' => $id]);
    }

    public function testCentreDistanceCannotExceedTheLength(): void
    {
        $response = $this->admin()->post(self::ADMIN, ['action' => 'create', 'center_distance_mm' => 160] + self::MODEL);
        self::assertSame(400, $response->status, $response->body);
        self::assertSame("L'entraxe ne peut pas dépasser la longueur de la poignée", $response->data()['error'] ?? null);
    }

    private function dimensions(int $id): array
    {
        foreach ($this->admin()->get(self::ADMIN)->data()['data'] as $model) {
            if ($model['id'] === $id) {
                return [$model['length_mm'], $model['center_distance_mm'], $model['width_mm'], $model['projection_mm']];
            }
        }
        self::fail('Model ' . $id . ' missing from the admin list');
    }
}
