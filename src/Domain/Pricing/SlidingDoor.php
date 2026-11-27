<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final class SlidingDoor
{
    public const CONTENT = 'sliding_door';
    private const THREE_PANELS_ABOVE_MM = 1600;
    private const OVERLAP_MM = 30;
    private const SIDES_MM = 38;

    public static function panels(float $width): int
    {
        return $width - self::SIDES_MM > self::THREE_PANELS_ABOVE_MM ? 3 : 2;
    }

    public static function panelSurface(float $width, float $height): float
    {
        return ($width + (self::panels($width) - 1) * self::OVERLAP_MM) * $height / 1_000_000;
    }

    public static function rails(FurniturePricingGrid $grid, float $width): float
    {
        return 2 * $width / 1000 * $grid->get('fronts', 'sliding', 'rail_price_per_m');
    }

    public static function grooves(FurniturePricingGrid $grid, float $width): float
    {
        return 2 * $width / 1000 * $grid->get('fronts', 'sliding', 'groove_price_per_m');
    }
}
