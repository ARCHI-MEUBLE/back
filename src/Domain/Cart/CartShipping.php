<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Pricing\FurniturePricing;
use App\Domain\Pricing\ShippingCalculator;

final class CartShipping
{
    public function __construct(private readonly FurniturePricing $pricing) {}

    public function quote(array $configurationItems, float $parcelSubtotal, bool $hasParcelItems): array
    {
        $calculator = $this->pricing->shipping();
        $furniture = 0.0;
        foreach ($configurationItems as $item) {
            $configuration = is_array($item['configuration'] ?? null) ? $item['configuration'] : [];
            if (($configuration['price_includes_delivery'] ?? false) === true) {
                continue;
            }
            $furniture += self::furnitureFor($calculator, $configuration['config_data'] ?? null) * max(0, (int) ($item['quantity'] ?? 0));
        }
        $parcel = $hasParcelItems ? $calculator->parcel(round($parcelSubtotal, 2)) : 0.0;
        return [
            'furniture' => round($furniture, 2),
            'parcel' => round($parcel, 2),
            'total' => round($furniture + $parcel, 2),
            'parcel_free_from' => $calculator->parcelFreeFrom(),
        ];
    }

    public function furniture(mixed $configData): float
    {
        return self::furnitureFor($this->pricing->shipping(), $configData);
    }

    private static function furnitureFor(ShippingCalculator $calculator, mixed $configData): float
    {
        if (is_string($configData)) {
            $configData = json_decode($configData, true);
        }
        $dimensions = is_array($configData) && is_array($configData['dimensions'] ?? null) ? $configData['dimensions'] : [];
        $width = is_numeric($dimensions['width'] ?? null) ? (float) $dimensions['width'] : 0.0;
        $height = is_numeric($dimensions['height'] ?? null) ? (float) $dimensions['height'] : 0.0;
        return $calculator->furniture($width, $height);
    }
}
