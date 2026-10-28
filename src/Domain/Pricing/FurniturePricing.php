<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final class FurniturePricing
{
    public function __construct(private readonly PricingConfigRepository $config) {}

    /** @return array{price: int, low: int, high: int, sigma: int, lines: list<array{key: string, label: string, amount: int}>} */
    public function estimate(array $configuration): array
    {
        return FurniturePriceCalculator::fromConfigRows($this->config->search(null, null, null, true))->estimate($configuration);
    }

    /** Saved configurations carry their dimensions; older payloads without them keep the price they were sent with. */
    public static function canEstimate(array $configuration): bool
    {
        $dimensions = $configuration['dimensions'] ?? null;
        return is_array($dimensions) && is_numeric($dimensions['width'] ?? null) && is_numeric($dimensions['height'] ?? null) && is_numeric($dimensions['depth'] ?? null);
    }
}
