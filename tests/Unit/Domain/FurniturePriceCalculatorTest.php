<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Pricing\FurniturePriceCalculator;
use App\Domain\Shared\DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FurniturePriceCalculatorTest extends TestCase
{
    use PricingFixtures;

    public static function referencePieces(): array
    {
        $shelves = static fn(int $n): array => self::split('horizontal', array_map(static fn(): array => self::leaf(), range(1, $n)));
        return [
            'TV unit with drawers and a door' => [self::config(1700, 600, 450, self::split('vertical', [
                self::split('horizontal', [self::leaf('drawer', ['handleType' => 'horizontal_bar']), self::leaf('drawer', ['handleType' => 'horizontal_bar'])]),
                self::leaf(),
                self::leaf('door', ['handleType' => 'knob']),
            ])), 1200, 1500],
            'catalogue TV unit, one drawer' => [self::config(1700, 730, 500, self::split('vertical', [self::leaf(), self::leaf('drawer', ['handleType' => 'horizontal_bar']), self::leaf()])), 1050, 1300],
            'open bookcase' => [self::config(1200, 2000, 350, self::split('vertical', [$shelves(5), $shelves(5)])), 1050, 1350],
            'sideboard with three doors' => [self::config(1800, 850, 450, self::split('vertical', array_fill(0, 3, self::split('horizontal', [self::leaf(), self::leaf()], ['doorContent' => 'door', 'handleType' => 'vertical_bar'])))), 1400, 1750],
            'large wardrobe' => [self::config(2600, 2200, 600, self::split('vertical', [
                self::split('horizontal', [self::leaf(), self::leaf('dressing')], ['doorContent' => 'door_double', 'handleType' => 'vertical_bar']),
                self::split('horizontal', [self::leaf(), self::leaf(), self::leaf('drawer'), self::leaf('drawer'), self::leaf('drawer')]),
                self::split('horizontal', [self::leaf(), self::leaf('dressing')], ['doorContent' => 'door_double', 'handleType' => 'vertical_bar']),
            ]), ['socle' => 'wood']), 3400, 4400],
            'small open cube' => [self::config(600, 600, 350, $shelves(2)), 600, 850],
        ];
    }

    #[DataProvider('referencePieces')]
    public function testReferencePiecesLandInTheirBand(array $config, int $min, int $max): void
    {
        $estimate = (new FurniturePriceCalculator())->estimate($config);

        self::assertGreaterThanOrEqual($min, $estimate['price']);
        self::assertLessThanOrEqual($max, $estimate['price']);
        self::assertSame(0, $estimate['price'] % 10);
    }

    public function testRangeIsPriceMinusAndPlusOneStandardDeviation(): void
    {
        $estimate = (new FurniturePriceCalculator())->estimate(self::config(1700, 600, 450, self::leaf('door')));

        self::assertGreaterThan(0, $estimate['sigma']);
        self::assertEqualsWithDelta($estimate['price'] - $estimate['sigma'], $estimate['low'], 10);
        self::assertEqualsWithDelta($estimate['price'] + $estimate['sigma'], $estimate['high'], 10);
        self::assertGreaterThan(0.08, $estimate['sigma'] / $estimate['price']);
        self::assertLessThan(0.14, $estimate['sigma'] / $estimate['price']);
        self::assertEqualsWithDelta($estimate['price'], array_sum(array_column($estimate['lines'], 'amount')), 10);
    }

    public function testLargerPiecesAndRicherMaterialsCostMore(): void
    {
        $calculator = self::calculator();
        $price = static fn(int $width, string $material = 'mdf_melamine'): int => $calculator->estimate(self::config($width, 800, 400, self::leaf(), ['materialKey' => $material]))['price'];

        self::assertLessThan($price(2000), $price(1200));
        self::assertLessThan($price(2800), $price(2000));
        self::assertLessThan($price(1500, 'mdf_melamine'), $price(1500, 'agglomere'));
        self::assertLessThan($price(1500, 'plaque_bois'), $price(1500, 'mdf_melamine'));
    }

    public function testMaterialLabelsFromTheConfiguratorAreRecognised(): void
    {
        $calculator = self::calculator();
        $byLabel = static fn(array $styling): int => $calculator->estimate(self::config(1500, 800, 400, self::leaf(), $styling + ['materialKey' => null]))['price'];

        self::assertSame($byLabel(['materialKey' => 'agglomere']), $byLabel(['finish' => 'Aggloméré']));
        self::assertSame($byLabel(['materialKey' => 'plaque_bois']), $byLabel(['materialLabel' => 'Plaqué bois']));
        self::assertSame($byLabel(['materialKey' => 'mdf_melamine']), $byLabel(['finish' => 'MDF + revêtement (mélaminé)']));
        self::assertNotSame($byLabel(['materialKey' => 'agglomere']), $byLabel(['materialKey' => 'plaque_bois']));
    }

    public function testGridFromTheDatabaseOverridesTheDefaults(): void
    {
        $config = self::config(1500, 800, 400, self::leaf());
        $default = (new FurniturePriceCalculator())->estimate($config)['price'];
        $calculator = FurniturePriceCalculator::fromConfigRows([self::grid('workshop', 'fixed', 'price', 700)]);

        self::assertSame($default + 100, $calculator->estimate($config)['price']);
    }

    public function testPriceIsRoundedWithTheGridStep(): void
    {
        $config = self::config(1500, 800, 400, self::leaf('drawer'));
        $byStep = static fn(float $step): array => FurniturePriceCalculator::fromConfigRows([self::grid('rounding', 'price', 'step', $step)])->estimate($config);

        self::assertSame(0, $byStep(50)['price'] % 50);
        self::assertSame(0, $byStep(50)['low'] % 50);
        self::assertEqualsWithDelta($byStep(1)['price'], $byStep(50)['price'], 25);
        self::assertSame($byStep(1)['price'], $byStep(0)['price']);
    }

    public static function invalidDimensions(): array
    {
        return [
            'too narrow' => [299, 800, 400], 'too wide' => [2801, 800, 400], 'too low' => [1500, 299, 400],
            'too high' => [1500, 2201, 400], 'too shallow' => [1500, 800, 199], 'too deep' => [1500, 800, 801],
        ];
    }

    #[DataProvider('invalidDimensions')]
    public function testDimensionsFollowTheConfiguratorLimits(int $width, int $height, int $depth): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/^Dimension invalide : la (largeur|hauteur|profondeur) doit être comprise entre \d+ et \d+ mm$/');
        (new FurniturePriceCalculator())->estimate(self::config($width, $height, $depth, self::leaf()));
    }

    public function testConfiguratorLimitsThemselvesAreAccepted(): void
    {
        self::assertGreaterThan(0, (new FurniturePriceCalculator())->estimate(self::config(300, 300, 200, self::leaf()))['price']);
        self::assertGreaterThan(0, (new FurniturePriceCalculator())->estimate(self::config(2800, 2200, 800, self::leaf()))['price']);
    }

    public function testOverlyDeepZoneTreesAreRejected(): void
    {
        $zone = self::leaf();
        for ($i = 0; $i < 20; $i++) {
            $zone = self::split('vertical', [$zone, self::leaf()]);
        }
        $this->expectException(DomainException::class);
        (new FurniturePriceCalculator())->estimate(self::config(1500, 800, 400, $zone));
    }

    public function testSplitRatiosAreNormalisedSoTheyCannotShrinkOrInflateThePrice(): void
    {
        $columns = static fn(array $extra): int => (new FurniturePriceCalculator())->estimate(self::config(1800, 800, 400, self::split('vertical', [
            self::leaf('drawer'), self::leaf('drawer'), self::leaf('drawer'),
        ], $extra)))['price'];

        $even = $columns([]);
        foreach ([[5, 5, 5], [300, 300, 300], [0, 0, 0], [-50, 100, 50], ['a', 1, 1]] as $ratios) {
            self::assertSame($even, $columns(['splitRatios' => $ratios]), json_encode($ratios, JSON_THROW_ON_ERROR));
        }
        self::assertSame($columns(['splitRatios' => [50, 25, 25]]), $columns(['splitRatios' => [2, 1, 1]]));
    }
}
