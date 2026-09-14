<?php

declare(strict_types=1);

namespace Tests\Contract\Configuration;

use Tests\Support\ContractTestCase;

final class ConfigurationsTest extends ContractTestCase
{
    public function testCustomerList(): void
    {
        $this->assertSnapshot('configurations.list.unauthorized', $this->client()->get('/backend/api/configurations/list.php'));
        $list = $this->customer()->get('/backend/api/configurations/list.php');
        $this->assertSnapshot('configurations.list', $list);
        self::assertNotEmpty($list->data()['configurations']);
        $this->assertSnapshot('configurations.one', $this->customer()->get('/backend/api/configurations/list.php?id=1'));
        $this->assertSnapshot('configurations.one.missing', $this->customer()->get('/backend/api/configurations/list.php?id=999999'));
    }

    public function testSaveUpdateDelete(): void
    {
        $payload = [
            'name' => 'Nouvelle config',
            'model_id' => 1,
            'prompt' => 'M1(1500,400,700)EFbV3(,T,)',
            'config_data' => ['dimensions' => ['width' => 1500], 'styling' => [], 'features' => []],
            'glb_url' => '/models/new.glb',
            'dxf_url' => null,
            'price' => 950.25,
            'thumbnail_url' => '/uploads/thumbs/new.png',
            'status' => 'en_attente_validation',
        ];
        $this->assertSnapshot('configurations.save.unauthorized', $this->client()->post('/backend/api/configurations/save.php', $payload));
        $created = $this->customer()->post('/backend/api/configurations/save.php', $payload);
        $this->assertSnapshot('configurations.save', $created);
        $id = (int) $created->data()['configuration']['id'];
        $this->assertSnapshot('configurations.save.invalid', $this->customer()->post('/backend/api/configurations/save.php', ['name' => 'sans prompt']));
        $this->assertSnapshot('configurations.save.update', $this->customer()->post('/backend/api/configurations/save.php', ['id' => $id, 'price' => 999] + $payload));
        $this->assertSnapshot('configurations.save.update.admin', $this->admin()->post('/backend/api/configurations/save.php', ['id' => $id, 'status' => 'validee'] + $payload));
        $this->assertSnapshot('configurations.save.update.missing', $this->customer()->post('/backend/api/configurations/save.php', ['id' => 999999] + $payload));
        $this->assertSnapshot('configurations.delete', $this->customer()->delete('/backend/api/configurations/list.php?id=' . $id));
        $this->assertSnapshot('configurations.delete.missing', $this->customer()->delete('/backend/api/configurations/list.php?id=' . $id));
    }

    public function testAdminViews(): void
    {
        $this->assertSnapshot('configurations.admin.unauthorized', $this->client()->get('/backend/api/admin/configurations.php'));
        $list = $this->admin()->get('/backend/api/admin/configurations.php');
        $this->assertSnapshot('configurations.admin.list', $list);
        self::assertNotEmpty($list->data()['configurations']);
        $this->assertSnapshot('configurations.admin.filtered', $this->admin()->get('/backend/api/admin/configurations.php?status=validee&limit=5&offset=0'));
        $this->assertSnapshot('configurations.admin.one', $this->admin()->get('/backend/api/admin/configurations.php?id=1'));
        $this->assertSnapshot('configurations.admin.status', $this->admin()->post('/backend/api/admin/update-configuration-status.php', ['id' => 2, 'status' => 'validee']));
        $this->assertSnapshot('configurations.admin.status.invalid', $this->admin()->post('/backend/api/admin/update-configuration-status.php', ['id' => 2, 'status' => 'nope']));
        $this->assertSnapshot('configurations.admin.status.unauthorized', $this->client()->post('/backend/api/admin/update-configuration-status.php', ['id' => 2, 'status' => 'validee']));
    }

    public function testCreateOrderFromConfiguration(): void
    {
        $this->assertSnapshot('configurations.admin.order.unauthorized', $this->client()->post('/backend/api/admin/create-order-from-config.php', ['configuration_id' => 3]));
        $created = $this->admin()->post('/backend/api/admin/create-order-from-config.php', ['configuration_id' => 3]);
        $this->assertSnapshot('configurations.admin.order', $created);
        self::assertArrayHasKey('order_number', $created->data()['data']);
        $this->assertSnapshot('configurations.admin.order.missing', $this->admin()->post('/backend/api/admin/create-order-from-config.php', ['configuration_id' => 999999]));
    }

    public function testApiClientConfigurations(): void
    {
        $this->assertSnapshot('configurations.api.list', $this->client()->get('/api/configurations'));
        $this->assertSnapshot('configurations.api.one', $this->client()->get('/api/configurations?id=1'));
        $created = $this->client()->post('/api/configurations', ['user_session' => 'sess-1', 'prompt' => 'M1(1000,400,600)EFb', 'price' => 500, 'glb_url' => '/models/x.glb', 'metadata' => ['a' => 1]]);
        $this->assertSnapshot('configurations.api.create', $created);
        $this->assertSnapshot('configurations.api.by-session', $this->client()->get('/api/configurations?session=sess-1'));
        $this->assertSnapshot('configurations.api.create.invalid', $this->client()->post('/api/configurations', ['price' => 5]));
    }
}
