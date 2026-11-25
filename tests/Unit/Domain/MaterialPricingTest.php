<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Pricing\FurniturePriceCalculator;
use App\Domain\Pricing\MaterialPrices;
use PHPUnit\Framework\TestCase;

final class MaterialPricingTest extends TestCase
{
    use PricingFixtures;

    private const AGGLOMERE = 1;
    private const PLAQUE_BOIS = 3;
    private const UNPRICED = 4;

    public function testStructureAndBackTakeTheMaterialOfTheirColour(): void
    {
        self::assertSame(165, self::panels(['colorId' => self::AGGLOMERE]));
        self::assertSame(275, self::panels(['colorId' => self::PLAQUE_BOIS]));
        self::assertSame(275, self::panels(['colorId' => null, 'selectedColorId' => self::PLAQUE_BOIS]));
        self::assertSame(255, self::panels(['colorId' => self::AGGLOMERE], self::parts(['structure' => self::PLAQUE_BOIS])));
        self::assertSame(185, self::panels(['colorId' => self::AGGLOMERE], self::parts(['back' => self::PLAQUE_BOIS])));
        self::assertSame(165, self::panels(['colorId' => self::AGGLOMERE], ['useMultiColor' => false] + self::parts(['structure' => self::PLAQUE_BOIS])));
    }

    public function testShelvesAndUprightsTakeTheShelvesColour(): void
    {
        $shelf = self::split('horizontal', [self::leaf(), self::leaf()]);
        $plain = self::panels(['colorId' => self::AGGLOMERE], [], $shelf);

        self::assertSame($plain + 25, self::panels(['colorId' => self::AGGLOMERE], self::parts(['shelves' => self::PLAQUE_BOIS]), $shelf));
    }

    public function testDoorsAndDrawersTakeTheirOwnColour(): void
    {
        $main = ['colorId' => self::AGGLOMERE];

        self::assertSame(143, self::fronts(self::leaf('door'), $main));
        self::assertSame(195, self::fronts(self::leaf('door'), $main, self::parts(['doors' => self::PLAQUE_BOIS])));
        self::assertSame(188, self::fronts(self::leaf('drawer'), $main, self::parts(['doors' => self::PLAQUE_BOIS])));
        self::assertSame(240, self::fronts(self::leaf('drawer'), $main, self::parts(['drawers' => self::PLAQUE_BOIS])));
    }

    public function testAFacadeWithItsOwnColourTakesItsMaterial(): void
    {
        $main = ['colorId' => self::AGGLOMERE];
        $own = ['zoneColor' => ['colorId' => self::PLAQUE_BOIS, 'hex' => '#7b4b30']];

        self::assertSame(195, self::fronts(self::leaf('door', $own), $main));
        self::assertSame(240, self::fronts(self::leaf('drawer', $own), $main));
        self::assertSame(195, self::fronts(self::split('horizontal', [self::leaf(), self::leaf()], ['doorContent' => 'door'] + $own), $main));
        self::assertSame(143, self::fronts(self::leaf('door', ['zoneColor' => ['colorId' => 99]]), $main));
    }

    public function testUnknownOrUnpricedColoursFallBackWithoutTrustingTheClient(): void
    {
        self::assertSame(195, self::fronts(self::leaf('door'), ['colorId' => self::PLAQUE_BOIS], self::parts(['doors' => 99])));
        self::assertSame(275, self::panels(['colorId' => 99, 'materialKey' => 'plaque_bois']));
        self::assertSame(209, self::panels(['colorId' => self::UNPRICED, 'materialKey' => 'plaque_bois']));
        self::assertSame(440, self::panels(['colorId' => self::UNPRICED], [], null, [self::grid('materials', 'default', 'price_per_m2', 200)]));
        self::assertSame(209, (new FurniturePriceCalculator())->estimate(self::config(1000, 800, 500, self::leaf(), ['colorId' => self::PLAQUE_BOIS]))['lines'][1]['amount']);
    }

    public function testOldConfigurationsWithoutColourAreMatchedByMaterialName(): void
    {
        self::assertSame(165, self::panels(['materialKey' => 'agglomere']));
        self::assertSame(275, self::panels(['materialKey' => null, 'materialLabel' => 'Plaqué bois']));
        self::assertSame(209, self::panels(['materialKey' => 'mdf_melamine']));
        self::assertSame(165, self::panels(['materialKey' => 'Aggloméré hydrofuge']));
        self::assertSame(209, self::panels(['materialKey' => 'Laqué']));
        self::assertSame(440, self::panels(['materialKey' => 'Laqué'], [], null, [self::grid('materials', 'default', 'price_per_m2', 200)]));
    }

    public function testColourIdsToLoadAreReadFromEveryPartOfTheConfiguration(): void
    {
        $zone = self::split('vertical', [
            self::leaf('door', ['zoneColor' => ['colorId' => 7]]),
            self::leaf('drawer', ['zoneColor' => ['colorId' => 'x']]),
            self::leaf('drawer', ['zoneColor' => ['colorId' => -1]]),
        ]);
        $config = self::config(1000, 800, 500, $zone, ['colorId' => 3, 'selectedColorId' => '3'], self::parts(['doors' => 5, 'back' => null]));

        self::assertSame([3, 5, 7], MaterialPrices::colorIds($config));
        self::assertSame([], MaterialPrices::colorIds(['styling' => 'x', 'componentColors' => [1, 2]]));
    }

    public function testMaterialNamesAreComparedWithoutAccentsNorCase(): void
    {
        self::assertSame('mdf + revetement (melamine)', MaterialPrices::normalize(' MDF + revêtement (mélaminé) '));
        self::assertSame('plaque bois', MaterialPrices::normalize('plaque_bois'));
        self::assertSame('agglomere', MaterialPrices::normalize("Agglome\u{0301}re\u{0301}"));
    }

    private static function parts(array $colors): array
    {
        return ['useMultiColor' => true, 'componentColors' => array_map(static fn(?int $id): array => ['colorId' => $id, 'hex' => null], $colors)];
    }

    private static function panels(array $styling, array $extra = [], ?array $zone = null, array $grid = []): int
    {
        return self::line(self::calculator($grid)->estimate(self::config(1000, 800, 500, $zone ?? self::leaf(), $styling, $extra)), 'panels');
    }

    private static function fronts(array $zone, array $styling, array $extra = []): int
    {
        return self::line(self::calculator()->estimate(self::config(1000, 800, 500, $zone, $styling, $extra)), 'fronts');
    }
}
