<?php

declare(strict_types=1);

namespace Tests\Contract\Catalogue;

use Tests\Support\ContractTestCase;

final class SamplesTest extends ContractTestCase
{
    public function testPublicSamples(): void
    {
        $response = $this->client()->get('/backend/api/samples/index.php');

        $this->assertSnapshot('samples.public', $response);
        self::assertArrayHasKey('materials', $response->data());
        $this->assertSnapshot('samples.public', $this->client()->get('/backend/api/samples.php'));
        $this->assertSnapshot('samples.public', $this->client()->get('/api/samples'));
    }

    public function testAdminRequiresSession(): void
    {
        $this->assertSnapshot('samples.admin.unauthorized', $this->client()->get('/backend/api/admin/samples.php'));
    }

    public function testAdminCrud(): void
    {
        $admin = $this->admin();
        $this->assertSnapshot('samples.admin.list', $admin->get('/backend/api/admin/samples.php'));
        $type = $admin->post('/backend/api/admin/samples.php', ['action' => 'create_type', 'name' => 'Type test', 'material' => 'Agglomere', 'description' => 'Test', 'position' => 9, 'price_per_m2' => 45.5, 'unit_price' => 3.2]);
        $this->assertSnapshot('samples.admin.create-type', $type);
        $typeId = (int) ($type->data()['id'] ?? 0);
        self::assertGreaterThan(0, $typeId, $type->body);
        $this->assertSnapshot('samples.admin.update-type', $admin->post('/backend/api/admin/samples.php', ['action' => 'update_type', 'id' => $typeId, 'name' => 'Type test 2', 'material' => 'Agglomere', 'description' => 'Test', 'position' => 9, 'active' => true, 'price_per_m2' => 46, 'unit_price' => 3.5]));
        $color = $admin->post('/backend/api/admin/samples.php', ['action' => 'create_color', 'type_id' => $typeId, 'name' => 'Rouge', 'hex' => '#ff0000', 'image_url' => null, 'position' => 0, 'price_per_m2' => 46, 'unit_price' => 3.5]);
        $this->assertSnapshot('samples.admin.create-color', $color);
        $colorId = (int) ($color->data()['id'] ?? 0);
        self::assertGreaterThan(0, $colorId, $color->body);
        $this->assertSnapshot('samples.admin.update-color', $admin->post('/backend/api/admin/samples.php', ['action' => 'update_color', 'id' => $colorId, 'name' => 'Rouge vif', 'hex' => '#ee0000', 'active' => true, 'position' => 1]));
        $this->assertSnapshot('samples.admin.delete-color', $admin->post('/backend/api/admin/samples.php', ['action' => 'delete_color', 'id' => $colorId]));
        $this->assertSnapshot('samples.admin.delete-type', $admin->post('/backend/api/admin/samples.php', ['action' => 'delete_type', 'id' => $typeId]));
        $this->assertSnapshot('samples.admin.action.unknown', $admin->post('/backend/api/admin/samples.php', ['action' => 'nope']));
    }

    public function testAnalytics(): void
    {
        $this->assertSnapshot('samples.analytics.unauthorized', $this->client()->get('/backend/api/admin/samples/analytics.php'));
        $this->assertSnapshot('samples.analytics', $this->admin()->get('/backend/api/admin/samples/analytics.php'));
    }
}
