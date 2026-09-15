<?php

declare(strict_types=1);

namespace Tests\Contract\Content;

use Tests\Support\ContractTestCase;

final class ModelsTest extends ContractTestCase
{
    public function testPublicList(): void
    {
        $response = $this->client()->get('/backend/api/models.php');

        $this->assertSnapshot('models.list', $response);
        self::assertNotEmpty($response->data()['models']);
        $this->assertSnapshot('models.list', $this->client()->get('/api/models'));
    }

    public function testSingleModel(): void
    {
        $this->assertSnapshot('models.one', $this->client()->get('/api/models?id=1'));
        $this->assertSnapshot('models.one.missing', $this->client()->get('/api/models?id=999'));
    }

    public function testTemplates(): void
    {
        $this->assertSnapshot('templates.list', $this->client()->get('/backend/api/templates.php'));
    }

    public function testAnonymousCannotWrite(): void
    {
        $this->assertSnapshot('models.create.unauthorized', $this->client()->post('/backend/api/models.php', ['name' => 'X', 'prompt' => 'M1(1,1,1)Eb']));
    }

    public function testAdminCrud(): void
    {
        $admin = $this->admin();
        $created = $admin->post('/backend/api/models.php', [
            'name' => 'Buffet test',
            'description' => 'Buffet bas',
            'prompt' => 'M1(1800,450,800)EFbV3(P,T,P)',
            'category' => 'Meubles TV',
            'price' => 1250.5,
            'imagePath' => '/uploads/models/buffet.png',
            'hoverImagePath' => null,
        ]);
        $this->assertSnapshot('models.create', $created);
        $id = (int) $created->data()['model']['id'];
        $this->assertSnapshot('models.create.invalid', $admin->post('/backend/api/models.php', ['name' => 'Sans prompt']));
        $this->assertSnapshot('models.update', $admin->put('/backend/api/models.php?id=' . $id, [
            'id' => $id,
            'name' => 'Buffet test 2',
            'description' => 'Buffet bas',
            'prompt' => 'M1(1800,450,800)EFbV3(P,T,P)',
            'category' => 'Meubles TV',
            'price' => 1300,
            'image_url' => '/uploads/models/buffet.png',
            'config_data' => ['dimensions' => ['width' => 1800]],
        ]));
        $this->assertSnapshot('models.delete', $admin->delete('/backend/api/models.php?id=' . $id, ['id' => $id]));
        $this->assertSnapshot('models.delete.missing', $admin->delete('/backend/api/models.php?id=' . $id, ['id' => $id]));
    }

    public function testConfiguratorCanSaveModelAsAdmin(): void
    {
        $created = $this->admin()->post('/backend/api/models.php', [
            'name' => 'Depuis configurateur',
            'description' => '',
            'prompt' => 'M1(1200,350,650)EFbV4(,,T,)',
            'category' => 'Meubles TV',
            'price' => 699,
            'image_url' => '/uploads/models/conf.png',
            'config_data' => ['dimensions' => ['width' => 1200]],
        ]);

        $this->assertSnapshot('models.create.configurator', $created);
    }
}
