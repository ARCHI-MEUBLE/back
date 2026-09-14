<?php

declare(strict_types=1);

namespace Tests\Contract\Facade;

use Tests\Support\ContractTestCase;
use Tests\Support\DatabaseUrl;

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

    public function testFacadeDxfGeneratesRealDrawing(): void
    {
        $pdo = DatabaseUrl::connect(DatabaseUrl::fromEnv());
        $existingOrder = $pdo->query('SELECT id FROM orders LIMIT 1');
        $orderId = $existingOrder === false ? 0 : (int) $existingOrder->fetchColumn();
        if ($orderId === 0) {
            $pdo->exec("INSERT INTO customers (id, email, password_hash, first_name, last_name) VALUES (999, 'dxf@test.archimeuble.com', 'x', 'D', 'X') ON CONFLICT (id) DO NOTHING");
            $pdo->exec("INSERT INTO orders (id, customer_id, order_number, total_amount, shipping_address) VALUES (999, 999, 'ORD-DXF-TEST', 10, 'x') ON CONFLICT (id) DO NOTHING");
            $orderId = 999;
        }
        $config = json_encode(['width' => 600, 'height' => 800, 'depth' => 19, 'drillings' => [['x' => 50, 'y' => 50, 'diameter' => 26]], 'material' => ['name' => 'Chêne']]);
        $stmt = $pdo->prepare('INSERT INTO order_facade_items (order_id, config_data, quantity, unit_price, total_price) VALUES (?, ?, 1, 10, 10) RETURNING id');
        $stmt->execute([$orderId, $config]);
        $facadeItemId = (int) $stmt->fetchColumn();

        $response = $this->admin()->get('/backend/api/facades/dxf.php?facade_id=' . $facadeItemId);

        self::assertSame(200, $response->status, $response->body);
        self::assertSame('application/dxf', $response->contentType());
        self::assertStringContainsString('facade_' . $facadeItemId . '.dxf', (string) $response->header('Content-Disposition'));
        self::assertStringStartsWith('  0
SECTION
', $response->body);
        self::assertStringEndsWith('  0
EOF
', $response->body);
        self::assertStringContainsString('CIRCLE', $response->body);
    }

    public function testWritesRequireAdmin(): void
    {
        $this->assertSnapshot('facade-materials.write.unauthorized', $this->client()->post('/backend/api/facade-materials.php', ['name' => 'x', 'color_hex' => '#000']));
        $this->assertSnapshot('facade-drilling-types.write.unauthorized', $this->client()->post('/backend/api/facade-drilling-types.php', ['name' => 'x']));
        $this->assertSnapshot('facades.write.unauthorized', $this->client()->post('/backend/api/facades.php', ['name' => 'x', 'width' => 1, 'height' => 1, 'depth' => 1]));
    }
}
