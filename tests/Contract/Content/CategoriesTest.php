<?php

declare(strict_types=1);

namespace Tests\Contract\Content;

use Tests\Support\ContractTestCase;

final class CategoriesTest extends ContractTestCase
{
    public function testPublicListBothUrlForms(): void
    {
        $response = $this->client()->get('/backend/api/categories.php?active=true');

        $this->assertSnapshot('categories.list.active', $response);
        self::assertCount(1, $response->data()['categories']);
        $this->assertSnapshot('categories.list.active', $this->client()->get('/api/categories?active=true'));
        $all = $this->client()->get('/api/categories');
        $this->assertSnapshot('categories.list.all', $all);
        self::assertCount(2, $all->data()['categories']);
    }

    public function testSingleCategory(): void
    {
        $this->assertSnapshot('categories.one', $this->client()->get('/api/categories?id=1'));
        $this->assertSnapshot('categories.one.missing', $this->client()->get('/api/categories?id=999'));
    }

    public function testAnonymousCannotWrite(): void
    {
        $this->assertSnapshot('categories.create.unauthorized', $this->client()->post('/backend/api/categories.php', ['name' => 'X']));
    }

    public function testAdminCrud(): void
    {
        $admin = $this->admin();
        $created = $admin->post('/backend/api/categories.php', ['name' => 'Dressings', 'description' => 'Rangements', 'image_url' => '/uploads/categories/dressing.png']);
        $this->assertSnapshot('categories.create', $created);
        $id = $this->createdId($created);
        $this->assertSnapshot('categories.create.invalid', $admin->post('/backend/api/categories.php', ['description' => 'sans nom']));
        $this->assertSnapshot('categories.update', $admin->put('/backend/api/categories.php', ['id' => $id, 'name' => 'Dressings modernes', 'description' => 'Rangements', 'image_url' => null]));
        $this->assertSnapshot('categories.reorder', $admin->post('/backend/api/categories.php', ['action' => 'reorder', 'categoryIds' => [$id, 1, 2]]));
        $this->assertSnapshot('categories.delete', $admin->delete('/backend/api/categories.php?id=' . $id, ['id' => $id]));
        $this->assertSnapshot('categories.delete.missing', $admin->delete('/backend/api/categories.php?id=' . $id, ['id' => $id]));
    }

    private function createdId(\Tests\Support\ApiResponse $response): int
    {
        $data = $response->data();
        $category = $data['category'] ?? $data;
        self::assertIsArray($category);
        self::assertArrayHasKey('id', $category, $response->body);
        return (int) $category['id'];
    }
}
