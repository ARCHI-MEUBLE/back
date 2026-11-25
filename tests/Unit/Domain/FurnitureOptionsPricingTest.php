<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Pricing\FurniturePriceCalculator;
use PHPUnit\Framework\TestCase;

final class FurnitureOptionsPricingTest extends TestCase
{
    use PricingFixtures;

    public function testDeliveryIsChargedApartFromTheFurniturePrice(): void
    {
        $config = self::config(1700, 600, 450, self::leaf('door'));
        $estimate = (new FurniturePriceCalculator())->estimate($config);
        $dearer = FurniturePriceCalculator::fromConfigRows([self::grid('shipping', 'furniture', 'price_per_m2', 100)])->estimate($config);

        self::assertSame(['workshop', 'panels', 'fronts', 'hardware'], array_column($estimate['lines'], 'key'));
        self::assertSame(46, $estimate['delivery']);
        self::assertSame(102, $dearer['delivery']);
        self::assertSame($estimate['price'], $dearer['price']);
        self::assertSame($estimate['high'], $dearer['high']);
    }

    public function testFlushMountingAddsItsSupplement(): void
    {
        $calculator = FurniturePriceCalculator::fromConfigRows([self::grid('options', 'flush_mounting', 'supplement', 80)]);
        $hardware = static fn(string $style): int => self::line($calculator->estimate(self::config(1000, 800, 500, self::leaf(), [], ['features' => ['mountingStyle' => $style]])), 'hardware');

        self::assertSame(80, $hardware('encastre'));
        self::assertSame(0, $hardware('applique'));
        self::assertSame(0, self::line((new FurniturePriceCalculator())->estimate(self::config(1000, 800, 500, self::leaf(), [], ['features' => ['mountingStyle' => 'encastre']])), 'hardware'));
    }

    public function testGlassDoorsAddTheirSupplementPerSquareMetre(): void
    {
        $calculator = FurniturePriceCalculator::fromConfigRows([self::grid('fronts', 'glass', 'supplement_per_m2', 100)]);
        $fronts = static fn(string $door): int => self::line($calculator->estimate(self::config(1000, 800, 500, self::leaf($door))), 'fronts');

        self::assertSame($fronts('door') + 80, $fronts('mirror_door'));
        self::assertSame($fronts('door_right') + 80, $fronts('mirror_door_right'));
    }

    public function testCompartmentWithoutBackIsNotChargedForIt(): void
    {
        $panels = static fn(array $zone): int => self::line((new FurniturePriceCalculator())->estimate(self::config(1000, 800, 500, $zone)), 'panels');
        $full = $panels(self::leaf());

        self::assertSame($full - 38, $panels(self::leaf('empty', ['noBack' => true])));
        self::assertSame($full + 38 - 19, $panels(self::split('vertical', [self::leaf(), self::leaf('empty', ['noBack' => true])])));
    }

    public function testOpenSpaceHasNeitherBackNorContent(): void
    {
        $open = self::leaf('drawer', ['isOpenSpace' => true, 'hasLight' => true, 'hasCableHole' => true]);
        $estimate = (new FurniturePriceCalculator())->estimate(self::config(1000, 800, 500, self::split('vertical', [$open, self::leaf()])));
        $plain = (new FurniturePriceCalculator())->estimate(self::config(1000, 800, 500, self::split('vertical', [self::leaf(), self::leaf()])));

        self::assertSame(self::line($plain, 'panels') - 19, self::line($estimate, 'panels'));
        self::assertSame(0, self::line($estimate, 'fronts'));
        self::assertSame(0, self::line($estimate, 'hardware'));
    }

    public function testWholeFurnitureDoorIsPricedLikeTheOneDrawnIn3d(): void
    {
        $calculator = self::calculator([], self::HANDLE_MODELS);
        $estimate = static fn(string $doorType, array $zone): array => $calculator->estimate(self::config(1000, 800, 500, $zone, ['colorId' => 2], ['features' => ['doorType' => $doorType]]));
        $empty = self::split('vertical', [self::leaf(), self::leaf()]);

        self::assertSame(164, self::line($estimate('single', $empty), 'fronts'));
        self::assertSame(25, self::line($estimate('single', $empty), 'hardware'));
        self::assertSame(229, self::line($estimate('double', $empty), 'fronts'));
        self::assertSame(50, self::line($estimate('double', $empty), 'hardware'));
        self::assertSame(0, self::line($estimate('none', $empty), 'fronts'));

        $withZoneDoor = self::split('vertical', [self::leaf('door'), self::leaf()]);
        self::assertSame(self::line($estimate('none', $withZoneDoor), 'fronts'), self::line($estimate('single', $withZoneDoor), 'fronts'));
        self::assertSame(self::line($estimate('none', $withZoneDoor), 'hardware'), self::line($estimate('double', $withZoneDoor), 'hardware'));
    }

    public function testADoorLeftOnASplitZoneIsPricedAsTheGroupDoorThe3dDraws(): void
    {
        $fronts = static fn(array $extra): int => self::line((new FurniturePriceCalculator())->estimate(self::config(1000, 800, 500, self::split('vertical', [self::leaf(), self::leaf()], $extra))), 'fronts');

        self::assertSame(164, $fronts(['content' => 'door']));
        self::assertSame(0, $fronts(['content' => 'door', 'doorContent' => 'empty']));
        self::assertSame(0, $fronts(['content' => 'drawer']));
    }

    public function testMetalFeetFollowTheGrid(): void
    {
        $hardware = static fn(int $width, array $grid = []): int => self::line(FurniturePriceCalculator::fromConfigRows($grid)->estimate(self::config($width, 800, 500, self::leaf(), ['socle' => 'metal'])), 'hardware');

        self::assertSame(80, $hardware(600));
        self::assertSame(120, $hardware(600, [self::grid('bases', 'metal', 'min_pairs', 3)]));
        self::assertSame(160, $hardware(2800));
        self::assertSame(80, $hardware(2800, [self::grid('bases', 'metal', 'foot_spacing_mm', 5000)]));
        self::assertGreaterThan(0, $hardware(2800, [self::grid('bases', 'metal', 'foot_spacing_mm', 0)]));
    }

    public function testDisabledOrMissingSettingsStillHaveAValue(): void
    {
        $config = self::config(1500, 800, 400, self::leaf('drawer'));

        self::assertSame((new FurniturePriceCalculator())->estimate($config), FurniturePriceCalculator::fromConfigRows([['category' => 'workshop', 'param_value' => 'x']])->estimate($config));
    }
}
