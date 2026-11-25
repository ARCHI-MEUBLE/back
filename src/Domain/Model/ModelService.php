<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Pricing\FurniturePricing;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use Throwable;

final class ModelService
{
    public function __construct(
        private readonly ModelRepository $models,
        private readonly FurniturePricing $pricing,
    ) {}

    public function find(int $id): array
    {
        $model = $this->models->findById($id);
        if ($model === null) {
            throw new NotFoundException('Modèle non trouvé');
        }
        return $this->withLivePrice($model);
    }

    public function list(): array
    {
        return array_map($this->withLivePrice(...), $this->models->all());
    }

    public function create(array $input): array
    {
        if (!isset($input['name']) || !isset($input['prompt'])) {
            throw new DomainException('Nom et prompt requis');
        }
        self::assertPromptHasBasePlank((string) $input['prompt']);
        $configData = self::encodeConfigData($input['config_data'] ?? null);
        $id = $this->models->create([
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'prompt' => $input['prompt'],
            'price' => $this->estimate($configData),
            'image_url' => $input['imageUrl'] ?? $input['image_url'] ?? $input['imagePath'] ?? $input['image_path'] ?? null,
            'category' => $input['category'] ?? null,
            'config_data' => $configData,
            'hover_image_url' => $input['hoverImageUrl'] ?? $input['hover_image_url'] ?? $input['hoverImagePath'] ?? $input['hover_image_path'] ?? null,
        ]);
        return $this->find($id);
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
        $configData = array_key_exists('config_data', $data) ? $data['config_data'] : (($this->models->findById($id) ?? [])['config_data'] ?? null);
        $estimate = $this->estimate(is_string($configData) ? $configData : null);
        if ($estimate !== null) {
            $data['price'] = $estimate;
        } else {
            unset($data['price']);
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

    private function withLivePrice(array $model): array
    {
        $estimate = $this->estimate(is_string($model['config_data'] ?? null) ? $model['config_data'] : null);
        if ($estimate !== null) {
            $model['price'] = number_format($estimate, 2, '.', '');
        }
        return $model;
    }

    private function estimate(?string $configData): ?float
    {
        $config = $configData === null ? null : json_decode($configData, true);
        if (!is_array($config) || !FurniturePricing::canEstimate($config)) {
            return null;
        }
        try {
            return (float) $this->pricing->estimate($config)['price'];
        } catch (Throwable $e) {
            error_log('Model price estimate failed: ' . $e->getMessage());
            return null;
        }
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
