<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final class FurnitureMaterials
{
    public function __construct(
        private readonly array $parts,
        private readonly array $colors = [],
    ) {}

    public static function uniform(float $price): self
    {
        return new self(array_fill_keys(MaterialPrices::PARTS, $price));
    }

    public function part(string $part): float
    {
        return is_numeric($this->parts[$part] ?? null) ? (float) $this->parts[$part] : 0.0;
    }

    public function facade(array $zone, string $part): float
    {
        $color = is_array($zone['zoneColor'] ?? null) ? MaterialPrices::colorId($zone['zoneColor']['colorId'] ?? null) : null;
        if ($color !== null && is_numeric($this->colors[$color] ?? null)) {
            return (float) $this->colors[$color];
        }
        return $this->part($part);
    }
}
