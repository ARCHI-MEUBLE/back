<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final class HandlePrices
{
    private const ORIENTATIONS = ['horizontal', 'vertical'];

    public function __construct(
        private readonly array $models = [],
        private readonly ?int $furnitureModel = null,
    ) {}

    public static function fromModelRows(array $rows): self
    {
        $prices = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_numeric($row['id'] ?? null) && is_numeric($row['price_per_unit'] ?? null)) {
                $prices[(int) $row['id']] = (float) $row['price_per_unit'];
            }
        }
        return new self($prices);
    }

    public function forFurniture(mixed $handles): self
    {
        $chosen = is_array($handles) ? self::modelId($handles['modelId'] ?? null) : null;
        $model = $chosen !== null && isset($this->models[$chosen]) ? $chosen : array_key_first($this->models);
        return new self($this->models, is_int($model) ? $model : null);
    }

    public static function modelId(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }

    public function price(FurniturePricingGrid $grid, array $zone): float
    {
        $customized = ($zone['handleModelId'] ?? null) !== null || in_array($zone['handleOrientation'] ?? null, self::ORIENTATIONS, true);
        $legacyType = is_string($zone['handleType'] ?? null) ? $zone['handleType'] : null;
        if (!$customized && $legacyType !== null && $grid->has('handles', $legacyType)) {
            return $grid->get('handles', $legacyType, 'price_per_unit');
        }
        $own = self::modelId($zone['handleModelId'] ?? null);
        if ($own !== null && isset($this->models[$own])) {
            return (float) $this->models[$own];
        }
        return $this->furnitureModel !== null ? (float) ($this->models[$this->furnitureModel] ?? 0.0) : 0.0;
    }
}
