<?php

declare(strict_types=1);

namespace Tests\Contract\Catalogue;

use Tests\Support\ContractTestCase;

final class CatalogueTest extends ContractTestCase
{
    public function testPublicCatalogue(): void
    {
        $this->assertSnapshot('catalogue.categories', $this->client()->get('/backend/api/catalogue.php?action=categories'));
        $list = $this->client()->get('/backend/api/catalogue.php?action=list&limit=10&offset=0');
        $this->assertSnapshot('catalogue.list', $list);
        self::assertSame(2, $list->data()['pagination']['total']);
        $this->assertSnapshot('catalogue.list.filtered', $this->client()->get('/backend/api/catalogue.php?action=list&limit=10&offset=0&category=Quincaillerie&search=poign'));
        $item = $this->client()->get('/backend/api/catalogue.php?action=item&id=1');
        $this->assertSnapshot('catalogue.item', $item);
        self::assertCount(2, $item->data()['data']['variations']);
        $this->assertSnapshot('catalogue.item.missing', $this->client()->get('/backend/api/catalogue.php?action=item&id=999'));
        $this->assertSnapshot('catalogue.action.unknown', $this->client()->get('/backend/api/catalogue.php?action=nope'));
    }

    public function testAdminRequiresSession(): void
    {
        $this->assertSnapshot('catalogue.admin.unauthorized', $this->client()->get('/backend/api/admin/catalogue.php?action=list'));
    }

    public function testAdminCrud(): void
    {
        $admin = $this->admin();
        $this->assertSnapshot('catalogue.admin.list', $admin->get('/backend/api/admin/catalogue.php?action=list'));
        $this->assertSnapshot('catalogue.admin.categories', $admin->get('/backend/api/admin/catalogue.php?action=categories'));
        $payload = [
            'name' => 'Pied métal',
            'category' => 'Pieds',
            'description' => 'Pied noir',
            'material' => 'Acier',
            'dimensions' => '100 mm',
            'unit_price' => 8.75,
            'unit' => 'piece',
            'stock_quantity' => 40,
            'min_order_quantity' => 4,
            'is_available' => true,
            'tags' => 'pied,metal',
            'variation_label' => 'Finition',
            'image_url' => '/uploads/catalogue/pied.png',
        ];
        $created = $admin->post('/backend/api/admin/catalogue.php?action=create', $payload);
        $this->assertSnapshot('catalogue.admin.create', $created);
        $id = (int) ($created->data()['data']['id'] ?? $created->data()['id'] ?? 0);
        self::assertGreaterThan(0, $id, $created->body);
        $this->assertSnapshot('catalogue.admin.create.invalid', $admin->post('/backend/api/admin/catalogue.php?action=create', ['name' => 'Sans prix']));
        $this->assertSnapshot('catalogue.admin.update', $admin->put('/backend/api/admin/catalogue.php?action=update&id=' . $id, ['unit_price' => 9.5] + $payload));
        $this->assertSnapshot('catalogue.admin.variations.list', $admin->get('/backend/api/admin/catalogue-variations.php?action=list&item_id=1'));
        $variation = $admin->post('/backend/api/admin/catalogue-variations.php?action=add', ['catalogue_item_id' => $id, 'color_name' => 'Blanc', 'image_url' => '/uploads/catalogue/pied-blanc.png', 'is_default' => true]);
        $this->assertSnapshot('catalogue.admin.variations.add', $variation);
        $variationId = (int) ($variation->data()['data']['id'] ?? $variation->data()['id'] ?? 0);
        self::assertGreaterThan(0, $variationId, $variation->body);
        $this->assertSnapshot('catalogue.admin.variations.delete', $admin->delete('/backend/api/admin/catalogue-variations.php?action=delete&id=' . $variationId));
        $this->assertSnapshot('catalogue.admin.delete', $admin->delete('/backend/api/admin/catalogue.php?action=delete&id=' . $id));
        $this->assertSnapshot('catalogue.admin.delete.missing', $admin->delete('/backend/api/admin/catalogue.php?action=delete&id=' . $id));
    }

    public function testVariationsRequireAdmin(): void
    {
        $this->assertSnapshot('catalogue.admin.variations.unauthorized', $this->client()->get('/backend/api/admin/catalogue-variations.php?action=list&item_id=1'));
    }
}
