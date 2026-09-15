<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

final class CatalogueItemInput
{
    public static function values(array $data): array
    {
        $isAvailable = (isset($data['is_available']) && $data['is_available'] !== '')
            ? filter_var($data['is_available'], FILTER_VALIDATE_BOOLEAN)
            : true;
        return [
            $data['name'] ?? '',
            $data['category'] ?? '',
            $data['description'] ?? null,
            $data['material'] ?? null,
            $data['dimensions'] ?? null,
            isset($data['unit_price']) ? (float) $data['unit_price'] : 0.0,
            $data['unit'] ?? 'pièce',
            (int) ($data['stock_quantity'] ?? 0),
            (int) ($data['min_order_quantity'] ?? 1),
            $isAvailable,
            $data['image_url'] ?? null,
            isset($data['weight']) ? (float) $data['weight'] : null,
            $data['tags'] ?? null,
            $data['variation_label'] ?? 'Couleur / Finition',
        ];
    }
}
