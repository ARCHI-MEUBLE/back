<?php

declare(strict_types=1);

namespace Tests\Contract\Catalogue;

use Tests\Support\ApiResponse;
use Tests\Support\ContractTestCase;

final class HandleModelImportTest extends ContractTestCase
{
    private const ADMIN = '/backend/api/admin/handle-models.php';
    private const NEW_ROW = [
        'line' => 31, 'reference' => 'TEST-IMPORT-1', 'name' => 'Poignée importée', 'shape' => 'bar', 'finish_hex' => '#123456',
        'length_mm' => 170, 'center_distance_mm' => 160, 'price_per_unit' => 21.5, 'active' => true,
        'image_url' => '/images/handles/lmc/flat-chrome.webp', 'supplier_url' => 'https://www.lmcstore.com/fr/poignee.html',
    ];

    public function testTheLmcCatalogueIsOfferedWithAPhotoForEachFinish(): void
    {
        $lmc = array_values(array_filter($this->adminModels(), static fn(array $model): bool => str_starts_with((string) $model['reference'], 'POI')));

        self::assertCount(29, $lmc);
        foreach ($lmc as $model) {
            self::assertMatchesRegularExpression('#^/images/handles/lmc/[a-z0-9-]+\.webp$#', (string) $model['image_url'], $model['name']);
            self::assertStringStartsWith('https://www.lmcstore.com/fr/', (string) $model['supplier_url'], $model['name']);
        }
        $byReference = array_column($lmc, null, 'reference');
        self::assertSame([120, null, 40, 1], self::dimensions($byReference['POI447IN']));
        self::assertSame([1100, null, null, null], self::dimensions($byReference['POI4491100BL']));
        self::assertSame([223, 200, 20, 40], self::dimensions($byReference['POI117200OR']));
        self::assertSame(1, $byReference['POI9409106AL']['position']);
    }

    public function testAPreviewSavesNothing(): void
    {
        $existing = $this->adminModels()[0];
        $response = $this->import([self::NEW_ROW, ['line' => 2, 'reference' => $existing['reference'], 'name' => $existing['name']]], true);

        self::assertSame(200, $response->status, $response->body);
        $data = $response->data();
        self::assertSame([1, 0, 1, false], [$data['created'], $data['updated'], $data['unchanged'], $data['saved']]);
        self::assertSame(['line' => 31, 'name' => 'Poignée importée', 'action' => 'create'], $data['changes'][0]);
        self::assertNotContains('Poignée importée', array_column($this->adminModels(), 'name'));
    }

    public function testNothingIsSavedWhileARowIsWrong(): void
    {
        $wrong = ['line' => 7, 'reference' => 'TEST-IMPORT-2', 'name' => 'Forme inconnue', 'shape' => 'hook', 'finish_hex' => '#000000', 'length_mm' => 100];
        $twin = ['line' => 9] + self::NEW_ROW;
        $data = $this->import([self::NEW_ROW, $wrong, $twin], false)->data();

        self::assertFalse($data['saved']);
        self::assertSame([7, 9], array_column($data['errors'], 'line'));
        self::assertStringStartsWith('Forme de poignée invalide', $data['errors'][0]['message']);
        self::assertSame('Même référence qu\'à la ligne 31', $data['errors'][1]['message']);
        self::assertNotContains('Poignée importée', array_column($this->adminModels(), 'name'));
    }

    public function testImportCreatesAndUpdatesByReferenceAndKeepsWhatTheFileLeavesOut(): void
    {
        $created = $this->import([self::NEW_ROW], false)->data();
        self::assertSame([1, true], [$created['created'], $created['saved']]);
        $model = $this->find('TEST-IMPORT-1');
        self::assertSame(['21.50', true, 'https://www.lmcstore.com/fr/poignee.html'], [$model['price_per_unit'], $model['active'], $model['supplier_url']]);

        $renamed = ['line' => 31, 'reference' => 'TEST-IMPORT-1', 'name' => 'Poignée importée bis', 'shape' => 'bar', 'finish_hex' => '#123456', 'length_mm' => 170, 'active' => false];
        $updated = $this->import([$renamed], false)->data();
        self::assertSame([0, 1], [$updated['created'], $updated['updated']]);
        $model = $this->find('TEST-IMPORT-1');
        self::assertSame(['Poignée importée bis', false, '21.50', $model['position']], [$model['name'], $model['active'], $model['price_per_unit'], $model['position']]);

        $this->admin()->post(self::ADMIN, ['action' => 'delete', 'id' => $model['id']]);
    }

    public function testAnEmptyOrHugeFileIsRefused(): void
    {
        self::assertSame(400, $this->import([], false)->status);
        self::assertSame(400, $this->import(array_fill(0, 501, self::NEW_ROW), true)->status);
    }

    private function import(array $rows, bool $dryRun): ApiResponse
    {
        return $this->admin()->post(self::ADMIN, ['action' => 'import', 'dryRun' => $dryRun, 'rows' => $rows]);
    }

    private function adminModels(): array
    {
        return $this->admin()->get(self::ADMIN)->data()['data'];
    }

    private function find(string $reference): array
    {
        foreach ($this->adminModels() as $model) {
            if ($model['reference'] === $reference) {
                return $model;
            }
        }
        self::fail('Missing ' . $reference);
    }

    private static function dimensions(array $model): array
    {
        return [$model['length_mm'], $model['center_distance_mm'], $model['width_mm'], $model['projection_mm']];
    }
}
