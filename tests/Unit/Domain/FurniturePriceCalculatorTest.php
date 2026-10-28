<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Pricing\FurniturePriceCalculator;
use App\Domain\Shared\DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FurniturePriceCalculatorTest extends TestCase
{
    public static function referencePieces(): array
    {
        $shelves = static fn(int $n): array => self::split('horizontal', array_map(static fn(): array => self::leaf(), range(1, $n)));
        return [
            'TV unit with drawers and a door' => [self::config(1700, 600, 450, self::split('vertical', [
                self::split('horizontal', [self::leaf('drawer', ['handleType' => 'horizontal_bar']), self::leaf('drawer', ['handleType' => 'horizontal_bar'])]),
                self::leaf(),
                self::leaf('door', ['handleType' => 'knob']),
            ])), 1200, 1500],
            'catalogue TV unit, one drawer' => [self::config(1700, 730, 500, self::split('vertical', [self::leaf(), self::leaf('drawer', ['handleType' => 'horizontal_bar']), self::leaf()])), 1150, 1350],
            'open bookcase' => [self::config(1200, 2000, 350, self::split('vertical', [$shelves(5), $shelves(5)])), 1200, 1500],
            'sideboard with three doors' => [self::config(1800, 850, 450, self::split('vertical', array_fill(0, 3, self::split('horizontal', [self::leaf(), self::leaf()], ['doorContent' => 'door', 'handleType' => 'vertical_bar'])))), 1400, 1750],
            'large wardrobe' => [self::config(2600, 2300, 600, self::split('vertical', [
                self::split('horizontal', [self::leaf(), self::leaf('dressing')], ['doorContent' => 'door_double', 'handleType' => 'vertical_bar']),
                self::split('horizontal', [self::leaf(), self::leaf(), self::leaf('drawer'), self::leaf('drawer'), self::leaf('drawer')]),
                self::split('horizontal', [self::leaf(), self::leaf('dressing')], ['doorContent' => 'door_double', 'handleType' => 'vertical_bar']),
            ]), 'mdf_melamine', 'wood'), 3500, 4600],
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
        $calculator = new FurniturePriceCalculator();
        $price = static fn(int $width, string $material = 'mdf_melamine'): int => $calculator->estimate(self::config($width, 800, 400, self::leaf(), $material))['price'];

        self::assertLessThan($price(2000), $price(1200));
        self::assertLessThan($price(2800), $price(2000));
        self::assertLessThan($price(1500, 'mdf_melamine'), $price(1500, 'agglomere'));
        self::assertLessThan($price(1500, 'plaque_bois'), $price(1500, 'mdf_melamine'));
    }

    public function testMaterialLabelsFromTheConfiguratorAreRecognised(): void
    {
        $calculator = new FurniturePriceCalculator();
        $byLabel = static fn(string $label): int => $calculator->estimate(['styling' => ['finish' => $label]] + self::config(1500, 800, 400, self::leaf()))['price'];

        self::assertSame($byLabel('agglomere'), $byLabel('Aggloméré'));
        self::assertSame($byLabel('plaque_bois'), $byLabel('Plaqué bois'));
        self::assertSame($byLabel('mdf_melamine'), $byLabel('MDF + revêtement (mélaminé)'));
    }

    public function testGridFromTheDatabaseOverridesTheDefaults(): void
    {
        $config = self::config(1500, 800, 400, self::leaf());
        $default = (new FurniturePriceCalculator())->estimate($config)['price'];
        $calculator = FurniturePriceCalculator::fromConfigRows([['category' => 'workshop', 'item_type' => 'fixed', 'param_name' => 'price', 'param_value' => '700.000000']]);

        self::assertSame($default + 100, $calculator->estimate($config)['price']);
    }

    public function testInvalidDimensionsAreRejected(): void
    {
        $this->expectException(DomainException::class);
        (new FurniturePriceCalculator())->estimate(self::config(1500, 20, 400, self::leaf()));
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

    private static function config(int $width, int $height, int $depth, array $zone, string $material = 'mdf_melamine', string $socle = 'none'): array
    {
        return [
            'dimensions' => ['width' => $width, 'height' => $height, 'depth' => $depth],
            'styling' => ['materialKey' => $material, 'socle' => $socle],
            'features' => ['doorType' => 'none'],
            'advancedZones' => $zone,
        ];
    }

    private static function leaf(?string $content = null, array $extra = []): array
    {
        return ['id' => 'z', 'type' => 'leaf', 'content' => $content ?? 'empty'] + $extra;
    }

    private static function split(string $type, array $children, array $extra = []): array
    {
        return ['id' => 'z', 'type' => $type, 'children' => $children] + $extra;
    }
}
