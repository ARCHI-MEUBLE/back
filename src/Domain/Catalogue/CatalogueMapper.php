<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

final class CatalogueMapper
{
    public const MATERIALS = ['Aggloméré', 'MDF + Revêtement (mélaminé)', 'Plaqué bois'];

    public static function variation(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'color_name' => $row['color_name'],
            'image_url' => $row['image_url'],
            'is_default' => (bool) $row['is_default'],
        ];
    }

    public static function withVariations(array $items, array $variationsByItemId): array
    {
        foreach ($items as &$item) {
            $forItem = $variationsByItemId[$item['id']] ?? [];
            $item['variations'] = array_map(self::variation(...), $forItem);
        }
        return $items;
    }

    public static function groupByItem(array $variations): array
    {
        $grouped = [];
        foreach ($variations as $variation) {
            $grouped[$variation['catalogue_item_id']][] = $variation;
        }
        return $grouped;
    }
}
