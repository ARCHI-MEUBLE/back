<?php

declare(strict_types=1);

namespace Tests\Contract\Facade;

use Tests\Support\ContractTestCase;

final class FacadesTest extends ContractTestCase
{
    public function testMaterialsPublicRead(): void
    {
        $active = $this->client()->get('/backend/api/facade-materials.php?active=1');
        $this->assertSnapshot('facade-materials.active', $active);
        self::assertCount(1, $active->data()['data']);
        $all = $this->client()->get('/backend/api/facade-materials.php');
        $this->assertSnapshot('facade-materials.all', $all);
        self::assertCount(2, $all->data()['data']);
        $this->assertSnapshot('facade-materials.one', $this->client()->get('/backend/api/facade-materials.php?id=1'));
    }

    public function testMaterialsAdminCrud(): void
    {
        $admin = $this->admin();
        $payload = ['name' => 'Noir mat', 'price_per_m2' => 180, 'is_active' => true, 'color_hex' => '#111111', 'texture_url' => '/textures/noir.png', 'price_modifier' => 5];
        $created = $admin->post('/backend/api/facade-materials.php', $payload);
        $this->assertSnapshot('facade-materials.create', $created);
        $id = (int) ($created->data()['data']['id'] ?? $created->data()['id'] ?? 0);
        self::assertGreaterThan(0, $id, $created->body);
        $this->assertSnapshot('facade-materials.create.invalid', $admin->post('/backend/api/facade-materials.php', ['name' => 'Sans couleur']));
        $this->assertSnapshot('facade-materials.update', $admin->put('/backend/api/facade-materials.php/' . $id, ['name' => 'Noir profond'] + $payload));
        $this->assertSnapshot('facade-materials.update', $admin->put('/api/facade-materials/' . $id, ['name' => 'Noir profond 2'] + $payload));
        $this->assertSnapshot('facade-materials.delete', $admin->delete('/backend/api/facade-materials.php?id=' . $id));
        $this->assertSnapshot('facade-materials.delete.missing', $admin->delete('/backend/api/facade-materials.php?id=' . $id));
    }

    public function testSettings(): void
    {
        $settings = $this->client()->get('/backend/api/facade-settings.php');
        $this->assertSnapshot('facade-settings.list', $settings);
        $keys = array_column($settings->data()['data'], 'setting_key');
        self::assertContains('hinge_base_price', $keys);
        $this->assertSnapshot('facade-settings.update', $this->admin()->put('/backend/api/facade-settings.php', ['setting_key' => 'hinge_base_price', 'setting_value' => '6']));
        $this->assertSnapshot('facade-settings.update.invalid', $this->admin()->put('/backend/api/facade-settings.php', ['setting_value' => '6']));
    }

    public function testDrillingTypesAndFacades(): void
    {
        $this->assertSnapshot('facade-drilling-types.list', $this->client()->get('/backend/api/facade-drilling-types.php'));
        $this->assertSnapshot('facade-drilling-types.active', $this->client()->get('/backend/api/facade-drilling-types.php?active=1'));
        $this->assertSnapshot('facades.list', $this->client()->get('/backend/api/facades.php'));
        $this->assertSnapshot('facades.list.active', $this->client()->get('/backend/api/facades.php?active=1'));
    }

    public function testFacadeDxfRequiresAdminAndExistingItem(): void
    {
        $this->assertSnapshot('facades.dxf.unauthorized', $this->client()->get('/backend/api/facades/dxf.php?facade_id=1'));
        $this->assertSnapshot('facades.dxf.missing', $this->admin()->get('/backend/api/facades/dxf.php?facade_id=999999'));
        $this->assertSnapshot('facades.dxf.no-id', $this->admin()->get('/backend/api/facades/dxf.php'));
    }
}
