<?php

declare(strict_types=1);

namespace Tests\Contract\Content;

use Tests\Support\ContractTestCase;

final class RealisationsTest extends ContractTestCase
{
    public function testPublicList(): void
    {
        $response = $this->client()->get('/backend/api/realisations.php');

        $this->assertSnapshot('realisations.public', $response);
        self::assertNotEmpty($response->data()['realisations']);
    }

    public function testAdminListRequiresSession(): void
    {
        $this->assertSnapshot('realisations.admin.unauthorized', $this->client()->get('/backend/api/admin/realisations.php'));
    }

    public function testAdminCrud(): void
    {
        $admin = $this->admin();
        $this->assertSnapshot('realisations.admin.list', $admin->get('/backend/api/admin/realisations.php'));
        $created = $admin->post('/backend/api/admin/realisations.php', [
            'titre' => 'Dressing chêne',
            'description' => 'Sur mesure',
            'date_projet' => '2026-05',
            'categorie' => 'Dressing',
            'lieu' => 'Roubaix',
            'dimensions' => '3000x600x2400',
            'image_url' => '/uploads/realisations/dressing.jpg',
        ]);
        $this->assertSnapshot('realisations.admin.create', $created);
        $id = (int) ($created->data()['id'] ?? $created->data()['realisation']['id'] ?? 0);
        self::assertGreaterThan(0, $id, $created->body);
        $this->assertSnapshot('realisations.admin.create.invalid', $admin->post('/backend/api/admin/realisations.php', ['description' => 'sans titre']));
        $this->assertSnapshot('realisations.admin.update', $admin->put('/backend/api/admin/realisations.php', [
            'id' => $id,
            'titre' => 'Dressing chêne clair',
            'description' => 'Sur mesure',
            'date_projet' => '2026-05',
            'categorie' => 'Dressing',
            'lieu' => 'Roubaix',
            'dimensions' => '3000x600x2400',
            'image_url' => '/uploads/realisations/dressing.jpg',
        ]));
        $this->assertSnapshot('realisations.admin.delete', $admin->delete('/backend/api/admin/realisations.php?id=' . $id));
        $this->assertSnapshot('realisations.admin.delete.missing', $admin->delete('/backend/api/admin/realisations.php?id=' . $id));
    }

    public function testImages(): void
    {
        $admin = $this->admin();
        $this->assertSnapshot('realisation-images.unauthorized', $this->client()->get('/backend/api/admin/realisation-images.php?realisation_id=1'));
        $list = $admin->get('/backend/api/admin/realisation-images.php?realisation_id=1');
        $this->assertSnapshot('realisation-images.list', $list);
        self::assertCount(2, $list->data()['images']);
        $created = $admin->post('/backend/api/admin/realisation-images.php', ['realisation_id' => 1, 'image_url' => '/uploads/realisations/biblio-3.jpg', 'ordre' => 3]);
        $this->assertSnapshot('realisation-images.create', $created);
        $id = (int) ($created->data()['id'] ?? $created->data()['image']['id'] ?? 0);
        self::assertGreaterThan(0, $id, $created->body);
        $this->assertSnapshot('realisation-images.reorder', $admin->put('/backend/api/admin/realisation-images.php', ['id' => $id, 'ordre' => 1]));
        $this->assertSnapshot('realisation-images.delete', $admin->delete('/backend/api/admin/realisation-images.php?id=' . $id));
        $this->assertSnapshot('realisation-images.delete.missing', $admin->delete('/backend/api/admin/realisation-images.php?id=' . $id));
    }
}
