<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Pricing\FurniturePriceCalculator;
use PHPUnit\Framework\TestCase;

final class FurnitureInstallationPricingTest extends TestCase
{
    use PricingFixtures;

    private const CHOSEN = ['features' => ['installation' => true]];

    public function testInstallationIsQuotedAlwaysButChargedOnlyWhenChosen(): void
    {
        $calculator = new FurniturePriceCalculator();
        $without = $calculator->estimate(self::config(1700, 600, 450, self::leaf('door')));
        $with = $calculator->estimate(self::config(1700, 600, 450, self::leaf('door'), [], self::CHOSEN));

        self::assertSame(90, $without['installation']);
        self::assertSame(90, $with['installation']);
        self::assertSame(['workshop', 'panels', 'fronts', 'hardware'], array_column($without['lines'], 'key'));
        self::assertSame(['workshop', 'panels', 'fronts', 'hardware', 'installation'], array_column($with['lines'], 'key'));
        self::assertSame(90, self::line($with, 'installation'));
        self::assertSame($without['price'] + 90, $with['price']);
        self::assertSame($without['delivery'], $with['delivery']);
        self::assertEqualsWithDelta($with['price'], array_sum(array_column($with['lines'], 'amount')), 10);
    }

    public function testInstallationFollowsTheSizeOfTheFurnitureAndTheGrid(): void
    {
        self::assertSame(286, (new FurniturePriceCalculator())->estimate(self::config(2600, 2200, 600, self::leaf(), [], self::CHOSEN))['installation']);

        $calculator = FurniturePriceCalculator::fromConfigRows([
            self::grid('installation', 'furniture', 'price_per_m2', 100),
            self::grid('installation', 'furniture', 'minimum', 0),
        ]);
        self::assertSame(102, self::line($calculator->estimate(self::config(1700, 600, 450, self::leaf(), [], self::CHOSEN)), 'installation'));
    }

    public function testOnlyAnExplicitChoiceAddsTheInstallation(): void
    {
        foreach ([false, 'true', 1, null] as $value) {
            $estimate = (new FurniturePriceCalculator())->estimate(self::config(1000, 800, 400, self::leaf(), [], ['features' => ['installation' => $value]]));
            self::assertNotContains('installation', array_column($estimate['lines'], 'key'));
        }
    }

    public function testTheInstallationWidensTheRangeLikeTheOtherLines(): void
    {
        $calculator = new FurniturePriceCalculator();
        $without = $calculator->estimate(self::config(2600, 2200, 600, self::leaf()));
        $with = $calculator->estimate(self::config(2600, 2200, 600, self::leaf(), [], self::CHOSEN));

        self::assertGreaterThanOrEqual($without['sigma'], $with['sigma']);
        self::assertGreaterThanOrEqual($with['price'], $with['high']);
        self::assertLessThanOrEqual($with['price'], $with['low']);
    }
}
