<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final class ShippingCalculator
{
    public function __construct(private readonly FurniturePricingGrid $grid = new FurniturePricingGrid()) {}

    public function furniture(float $widthMm, float $heightMm): float
    {
        $surface = max(0.0, $widthMm) / 1000 * max(0.0, $heightMm) / 1000;
        $amount = max($this->grid->get('shipping', 'furniture', 'minimum'), $surface * $this->grid->get('shipping', 'furniture', 'price_per_m2'));
        return round(max(0.0, $amount), 2);
    }

    public function installation(float $widthMm, float $heightMm): float
    {
        $surface = max(0.0, $widthMm) / 1000 * max(0.0, $heightMm) / 1000;
        $amount = max($this->grid->get('installation', 'furniture', 'minimum'), $surface * $this->grid->get('installation', 'furniture', 'price_per_m2'));
        return round(max(0.0, $amount), 2);
    }

    public function parcel(float $parcelSubtotal): float
    {
        $freeFrom = $this->parcelFreeFrom();
        if ($freeFrom !== null && $parcelSubtotal >= $freeFrom) {
            return 0.0;
        }
        return round(max(0.0, $this->grid->get('shipping', 'parcel', 'flat')), 2);
    }

    public function parcelFreeFrom(): ?float
    {
        $freeFrom = $this->grid->get('shipping', 'parcel', 'free_from');
        return $freeFrom > 0 ? $freeFrom : null;
    }
}
