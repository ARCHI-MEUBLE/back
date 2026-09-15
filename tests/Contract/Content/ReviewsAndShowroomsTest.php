<?php

declare(strict_types=1);

namespace Tests\Contract\Content;

use Tests\Support\ContractTestCase;

final class ReviewsAndShowroomsTest extends ContractTestCase
{
    public function testReviewsList(): void
    {
        $response = $this->client()->get('/api/avis');

        $this->assertSnapshot('avis.list', $response);
        self::assertGreaterThanOrEqual(2, count($response->data()));
        $this->assertSnapshot('avis.list', $this->client()->get('/backend/api/avis.php'));
    }

    public function testReviewCreateAndDelete(): void
    {
        $created = $this->client()->post('/api/avis', ['authorName' => 'Zoé', 'rating' => 5, 'text' => 'Parfait', 'date' => '2026-09-14']);
        $this->assertSnapshot('avis.create', $created);
        $id = $created->data()['id'] ?? $created->data()['review']['id'] ?? null;
        self::assertNotNull($id, $created->body);
        $this->assertSnapshot('avis.create.invalid', $this->client()->post('/api/avis', ['authorName' => 'Sans note']));
        $this->assertSnapshot('avis.delete.public', $this->client()->delete('/api/avis/' . $id));
    }

    public function testAdminDeleteReview(): void
    {
        $created = $this->client()->post('/api/avis', ['authorName' => 'Léo', 'rating' => 3, 'text' => 'Bien', 'date' => '2026-09-14']);
        $id = $created->data()['id'] ?? $created->data()['review']['id'];
        $this->assertSnapshot('avis.delete.admin.unauthorized', $this->client()->delete('/backend/api/admin/avis.php', ['id' => $id]));
        $this->assertSnapshot('avis.delete.admin', $this->admin()->delete('/backend/api/admin/avis.php', ['id' => $id]));
        $this->assertSnapshot('avis.delete.admin.missing', $this->admin()->delete('/backend/api/admin/avis.php', ['id' => $id]));
    }

    public function testShowrooms(): void
    {
        $this->assertSnapshot('showrooms.list', $this->client()->get('/api/showrooms'));
        $this->assertSnapshot('showrooms.one', $this->client()->get('/api/showrooms/1'));
        $this->assertSnapshot('showrooms.one.missing', $this->client()->get('/api/showrooms/999'));
    }
}
