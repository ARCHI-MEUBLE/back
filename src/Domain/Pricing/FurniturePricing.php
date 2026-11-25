<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Handle\HandleModelRepository;

final class FurniturePricing
{
    private readonly MaterialPriceRepository $materials;

    public function __construct(
        private readonly PricingConfigRepository $config,
        private readonly HandleModelRepository $handles,
        ?MaterialPriceRepository $materials = null,
    ) {
        $this->materials = $materials ?? $config->materialPrices();
    }

    public function estimate(array $configuration): array
    {
        $calculator = new FurniturePriceCalculator(
            $this->grid(),
            HandlePrices::fromModelRows($this->handles->activePrices()),
            MaterialPrices::fromRows($this->materials->forColors(MaterialPrices::colorIds($configuration))),
        );
        return $calculator->estimate($configuration);
    }

    public function grid(): FurniturePricingGrid
    {
        return $this->config->grid();
    }

    public function shipping(): ShippingCalculator
    {
        return new ShippingCalculator($this->grid());
    }

    public static function canEstimate(array $configuration): bool
    {
        $dimensions = $configuration['dimensions'] ?? null;
        return is_array($dimensions) && is_numeric($dimensions['width'] ?? null) && is_numeric($dimensions['height'] ?? null) && is_numeric($dimensions['depth'] ?? null);
    }
}
