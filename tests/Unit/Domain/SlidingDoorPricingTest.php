<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Pricing\FurniturePriceCalculator;
use App\Domain\Pricing\HandlePrices;
use App\Domain\Pricing\SlidingDoor;
use PHPUnit\Framework\TestCase;

final class SlidingDoorPricingTest extends TestCase
{
    use PricingFixtures;

    private const MODELS = [
        ['id' => 7, 'price_per_unit' => '40.00', 'shape' => 'bar'],
        ['id' => 8, 'price_per_unit' => '12.50', 'shape' => 'cup'],
        ['id' => 9, 'price_per_unit' => '20.00', 'shape' => 'cup'],
    ];

    public function testTwoPanelsUpToOneMetreSixtyOfOpeningThenThree(): void
    {
        self::assertSame(2, SlidingDoor::panels(1500));
        self::assertSame(2, SlidingDoor::panels(1638));
        self::assertSame(3, SlidingDoor::panels(1639));
        self::assertEqualsWithDelta(130 + 3.06 * 95 * 1.3, self::fronts(1500), 1);
        self::assertEqualsWithDelta(195 + 4.92 * 95 * 1.3, self::fronts(2400), 1);
    }

    public function testRailsAndGroovesArePricedPerMetreTopAndBottom(): void
    {
        $sliding = (new FurniturePriceCalculator())->estimate(self::config(1500, 2000, 600, self::leaf('sliding_door')));
        $door = (new FurniturePriceCalculator())->estimate(self::config(1500, 2000, 600, self::leaf('door')));

        self::assertSame(45, self::line($sliding, 'hardware'));
        self::assertSame(18, self::line($sliding, 'panels') - self::line($door, 'panels'));

        $free = FurniturePriceCalculator::fromConfigRows([
            self::grid('fronts', 'sliding', 'rail_price_per_m', 0),
            self::grid('fronts', 'sliding', 'groove_price_per_m', 0),
        ])->estimate(self::config(1500, 2000, 600, self::leaf('sliding_door')));
        self::assertSame(0, self::line($free, 'hardware'));
        self::assertSame(self::line($door, 'panels'), self::line($free, 'panels'));
    }

    public function testEachPanelGetsARecessedCupEvenWhenTheFurnitureHasABar(): void
    {
        self::assertSame(70, self::hardware(self::leaf('sliding_door'), ['modelId' => 7]));
        self::assertSame(85, self::hardware(self::leaf('sliding_door', ['handleModelId' => 9]), ['modelId' => 7]));
        self::assertSame(70, self::hardware(self::leaf('sliding_door', ['handleModelId' => 7]), ['modelId' => 7]));
        self::assertSame(40, self::hardware(self::leaf('door'), ['modelId' => 7]));
    }

    public function testWithoutRecessedModelsTheSlidingDoorHasNoHandle(): void
    {
        $prices = HandlePrices::fromModelRows([['id' => 7, 'price_per_unit' => '40.00', 'shape' => 'bar']])->forFurniture(['modelId' => 7]);

        self::assertSame(0.0, $prices->flushPrice([]));
        self::assertSame(12.5, HandlePrices::fromModelRows(self::MODELS)->forFurniture(['modelId' => 7])->flushPrice([]));
    }

    public function testASlidingDoorOnAGroupCoversItsWholeWidth(): void
    {
        $group = self::split('horizontal', [self::leaf(), self::leaf()], ['doorContent' => 'sliding_door']);
        $estimate = (new FurniturePriceCalculator())->estimate(self::config(2400, 2000, 600, $group));

        self::assertSame(72, self::line($estimate, 'hardware'));
    }

    private static function fronts(int $width): int
    {
        return self::line((new FurniturePriceCalculator())->estimate(self::config($width, 2000, 600, self::leaf('sliding_door'))), 'fronts');
    }

    private static function hardware(array $zone, array $handles): int
    {
        $config = self::config(1500, 2000, 600, $zone) + ['handles' => $handles];
        return self::line(FurniturePriceCalculator::fromConfigRows([], self::MODELS)->estimate($config), 'hardware');
    }
}
