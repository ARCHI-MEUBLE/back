<?php

declare(strict_types=1);

namespace Tests\Contract\Catalogue;

use Tests\Support\ApiResponse;
use Tests\Support\ContractTestCase;

final class HandleModelsTest extends ContractTestCase
{
    private const ADMIN = '/backend/api/admin/handle-models.php';
    private const MODEL = ['name' => 'Poignée test', 'shape' => 'bar', 'finish_hex' => '#123abc', 'length_mm' => 128, 'price_per_unit' => 18.5];

    public function testPublicListHasTheSeededModels(): void
    {
        $response = $this->client()->get('/backend/api/handle-models.php');

        $this->assertSnapshot('handle-models.public', $response);
        $models = $response->data()['data'];
        self::assertSame(['Barre ronde noire', 'Barre plate laiton', 'Profil aluminium'], array_slice(array_column($models, 'name'), 0, 3));
        self::assertSame(['bar', 'flat_bar', 'profile'], array_slice(array_column($models, 'shape'), 0, 3));
        self::assertArrayNotHasKey('price_per_unit', $models[0]);
        self::assertArrayNotHasKey('active', $models[0]);
        $this->assertSnapshot('handle-models.public', $this->client()->get('/api/handle-models'));
    }

    public function testAdminRequiresSession(): void
    {
        $this->assertSnapshot('handle-models.admin.unauthorized', $this->client()->get(self::ADMIN));
        $this->assertSnapshot('handle-models.admin.unauthorized', $this->client()->post(self::ADMIN, ['action' => 'create'] + self::MODEL));
    }

    public function testAdminCrud(): void
    {
        $admin = $this->admin();
        $this->assertSnapshot('handle-models.admin.list', $admin->get(self::ADMIN));
        $created = $admin->post(self::ADMIN, ['action' => 'create'] + self::MODEL);
        $this->assertSnapshot('handle-models.admin.create', $created);
        $id = (int) ($created->data()['id'] ?? 0);
        self::assertGreaterThan(0, $id, $created->body);
        $model = $this->adminModel($id);
        self::assertSame('18.50', $model['price_per_unit']);
        self::assertTrue($model['active']);
        self::assertGreaterThan(3, $model['position']);
        self::assertContains($id, array_column($this->client()->get('/backend/api/handle-models.php')->data()['data'], 'id'));

        $update = ['action' => 'update', 'id' => $id, 'name' => 'Bouton laiton', 'shape' => 'knob', 'active' => false, 'image_url' => '/uploads/handles/bouton.png'];
        $this->assertSnapshot('handle-models.admin.update', $admin->post(self::ADMIN, $update));
        $model = $this->adminModel($id);
        self::assertSame(['Bouton laiton', 'knob', null, false, '/uploads/handles/bouton.png', '#123abc'], [$model['name'], $model['shape'], $model['length_mm'], $model['active'], $model['image_url'], $model['finish_hex']]);
        self::assertNotContains($id, array_column($this->client()->get('/backend/api/handle-models.php')->data()['data'], 'id'));
        $admin->post(self::ADMIN, ['action' => 'update', 'id' => $id, 'image_url' => null]);
        self::assertNull($this->adminModel($id)['image_url']);

        $this->assertSnapshot('handle-models.admin.delete', $admin->post(self::ADMIN, ['action' => 'delete', 'id' => $id]));
        $this->assertSnapshot('handle-models.admin.not-found', $admin->post(self::ADMIN, ['action' => 'delete', 'id' => $id]));
        $this->assertSnapshot('handle-models.admin.not-found', $admin->post(self::ADMIN, ['action' => 'update', 'id' => $id, 'name' => 'X']));
        $this->assertSnapshot('handle-models.admin.action.unknown', $admin->post(self::ADMIN, ['action' => 'nope']));
    }

    public function testKnobNeedsNoLength(): void
    {
        $created = $this->admin()->post(self::ADMIN, ['action' => 'create', 'name' => 'Bouton rond', 'shape' => 'knob', 'finish_hex' => '#000000', 'length_mm' => 300]);
        self::assertSame(201, $created->status, $created->body);
        $id = (int) $created->data()['id'];
        self::assertNull($this->adminModel($id)['length_mm']);
        self::assertSame('0.00', $this->adminModel($id)['price_per_unit']);
        $this->admin()->post(self::ADMIN, ['action' => 'delete', 'id' => $id]);
    }

    public function testValidation(): void
    {
        $this->assertInvalid('Forme de poignée invalide (barre ronde, barre plate, profilé ou bouton)', ['shape' => 'hook']);
        $this->assertInvalid('Couleur de finition invalide (format #RRGGBB)', ['finish_hex' => 'red']);
        $this->assertInvalid('La longueur est obligatoire pour une barre ou un profilé', ['length_mm' => null]);
        $this->assertInvalid('La longueur doit être comprise entre 20 et 800 mm', ['length_mm' => 900]);
        $this->assertInvalid('Le prix doit être compris entre 0 et 1000 €', ['price_per_unit' => -1]);
        $this->assertInvalid('Le nom est obligatoire', ['name' => '  ']);
        $this->assertInvalid('Lien de photo invalide (adresse http(s) ou chemin /uploads/)', ['image_url' => 'javascript:alert(1)']);
        $this->assertInvalid('Ce nom de poignée existe déjà', ['name' => 'BARRE RONDE NOIRE']);
        $this->assertInvalid('Ce nom de poignée existe déjà', ['action' => 'update', 'id' => 2, 'name' => 'barre ronde noire']);
        $this->assertInvalid('Le champ id est invalide', ['action' => 'update', 'id' => 'abc']);
    }

    private function assertInvalid(string $message, array $changes): void
    {
        $response = $this->admin()->post(self::ADMIN, array_replace(['action' => 'create'] + self::MODEL, $changes));
        $this->assertSnapshot('handle-models.admin.invalid', $response);
        self::assertSame($message, $response->data()['error'] ?? null, $response->body);
    }

    private function adminModel(int $id): array
    {
        $list = $this->admin()->get(self::ADMIN);
        foreach ($this->models($list) as $model) {
            if ($model['id'] === $id) {
                return $model;
            }
        }
        self::fail('Model ' . $id . ' missing from the admin list');
    }

    private function models(ApiResponse $response): array
    {
        $data = $response->data()['data'] ?? [];
        return is_array($data) ? $data : [];
    }
}
