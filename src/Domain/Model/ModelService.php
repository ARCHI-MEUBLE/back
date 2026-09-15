<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;

final class ModelService
{
    public function __construct(private readonly ModelRepository $models) {}

    public function find(int $id): array
    {
        $model = $this->models->findById($id);
        if ($model === null) {
            throw new NotFoundException('Modèle non trouvé');
        }
        return $model;
    }

    public function list(): array
    {
        return $this->models->all();
    }

    public function create(array $input): array
    {
        if (!isset($input['name']) || !isset($input['prompt'])) {
            throw new DomainException('Nom et prompt requis');
        }
        self::assertPromptHasBasePlank((string) $input['prompt']);
        $id = $this->models->create([
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'prompt' => $input['prompt'],
            'price' => $input['price'] ?? $input['basePrice'] ?? $input['base_price'] ?? null,
            'image_url' => $input['imageUrl'] ?? $input['image_url'] ?? $input['imagePath'] ?? $input['image_path'] ?? null,
            'category' => $input['category'] ?? null,
            'config_data' => self::encodeConfigData($input['config_data'] ?? null),
            'hover_image_url' => $input['hoverImageUrl'] ?? $input['hover_image_url'] ?? $input['hoverImagePath'] ?? $input['hover_image_path'] ?? null,
        ]);
        return $this->models->findById($id) ?? [];
    }

    public function update(int $id, array $input): array
    {
        if (isset($input['prompt'])) {
            self::assertPromptHasBasePlank((string) $input['prompt']);
        }
        $data = self::updatePayload($input);
        if ($data === []) {
            throw new DomainException('Aucune donnée à mettre à jour');
        }
        if (!$this->models->update($id, $data)) {
            throw new DomainException('Erreur lors de la mise à jour du modèle', 500);
        }
        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->models->delete($id);
    }

    private static function assertPromptHasBasePlank(string $prompt): void
    {
        if (!str_contains($prompt, 'b')) {
            throw new DomainException('Le prompt doit contenir "b" (planche de base obligatoire)');
        }
    }

    private static function encodeConfigData(mixed $configData): ?string
    {
        if ($configData === null) {
            return null;
        }
        return is_string($configData) ? $configData : json_encode($configData, JSON_THROW_ON_ERROR);
    }

    private static function updatePayload(array $input): array
    {
        foreach (['imageUrl' => 'image_url', 'imagePath' => 'image_url', 'hoverImageUrl' => 'hover_image_url', 'hoverImagePath' => 'hover_image_url', 'basePrice' => 'price'] as $alias => $column) {
            if (isset($input[$alias])) {
                $input[$column] = $input[$alias];
            }
        }
        $data = [];
        foreach (['name', 'description', 'prompt', 'price', 'image_url', 'category', 'config_data', 'hover_image_url'] as $field) {
            if (isset($input[$field])) {
                $data[$field] = $field === 'config_data' ? self::encodeConfigData($input[$field]) : $input[$field];
            }
        }
        return $data;
    }
}
