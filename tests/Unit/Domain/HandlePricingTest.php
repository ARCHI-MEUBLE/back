<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Pricing\FurniturePriceCalculator;
use App\Domain\Pricing\FurniturePricingGrid;
use App\Domain\Pricing\HandlePrices;
use PHPUnit\Framework\TestCase;

final class HandlePricingTest extends TestCase
{
    private const MODELS = [['id' => 7, 'price_per_unit' => '40.00'], ['id' => 8, 'price_per_unit' => '12.50']];

    public function testZoneModelIsCharged(): void
    {
        self::assertSame(40, self::hardware(self::leaf('door', ['handleModelId' => 7])));
        self::assertSame(13, self::hardware(self::leaf('drawer', ['handleModelId' => 8])));
        self::assertSame(80, self::hardware(self::leaf('door_double', ['handleModelId' => 7])));
    }

    public function testZoneModelWinsOverTheFurnitureModel(): void
    {
        self::assertSame(13, self::hardware(self::leaf('door', ['handleModelId' => 8]), ['modelId' => 7]));
    }

    public function testDoorOnAGroupTakesItsOwnModel(): void
    {
        $group = ['id' => 'g', 'type' => 'horizontal', 'doorContent' => 'door', 'handleModelId' => 7, 'children' => [self::leaf(), self::leaf()]];

        self::assertSame(40, self::hardware($group, ['modelId' => 8]));
    }

    public function testLegacyHandleTypeKeepsItsGridPrice(): void
    {
        $knob = (int) (new FurniturePricingGrid())->get('handles', 'knob', 'price_per_unit');

        self::assertSame($knob, self::hardware(self::leaf('door', ['handleType' => 'knob']), ['modelId' => 7]));
        self::assertSame($knob, self::hardware(self::leaf('door', ['handleModelId' => 99, 'handleType' => 'knob']), ['modelId' => 7]));
    }

    public function testFurnitureModelAppliesToDoorsAndDrawersWithoutTheirOwnChoice(): void
    {
        $zone = self::split([self::leaf('door'), self::leaf('drawer'), self::leaf('drawer'), self::leaf()]);

        self::assertSame(120, self::hardware($zone, ['modelId' => 7, 'doorOrientation' => 'vertical', 'drawerOrientation' => 'horizontal']));
    }

    public function testInactiveOrUnknownModelsAreIgnored(): void
    {
        self::assertSame(40, self::hardware(self::leaf('door', ['handleModelId' => 99]), ['modelId' => 7]));
        self::assertSame(0, self::hardware(self::leaf('door', ['handleModelId' => 99]), ['modelId' => 98]));
        self::assertSame(0, self::hardware(self::leaf('door'), ['modelId' => '7']));
        self::assertSame(0, self::hardware(self::leaf('door')));
    }

    public function testOrientationDoesNotChangeThePrice(): void
    {
        $vertical = self::hardware(self::leaf('drawer', ['handleModelId' => 7, 'handleOrientation' => 'vertical']));

        self::assertSame($vertical, self::hardware(self::leaf('drawer', ['handleModelId' => 7, 'handleOrientation' => 'horizontal'])));
    }

    public function testPushFrontsNeverHaveAHandle(): void
    {
        $push = (int) (new FurniturePricingGrid())->get('fronts', 'push', 'supplement');

        foreach (['push_door', 'push_door_right', 'push_drawer'] as $content) {
            self::assertSame($push, self::hardware(self::leaf($content, ['handleModelId' => 7]), ['modelId' => 7]), $content);
        }
    }

    public function testCalculatorWithoutModelsIgnoresHandleChoices(): void
    {
        $config = self::config(self::leaf('door', ['handleModelId' => 7]), ['modelId' => 7]);

        self::assertSame(0, self::line((new FurniturePriceCalculator())->estimate($config), 'hardware'));
    }

    public function testModelRowsAreReadLikeTheDatabaseReturnsThem(): void
    {
        $prices = HandlePrices::fromModelRows([['id' => 3, 'price_per_unit' => '30.00'], ['id' => 'x', 'price_per_unit' => '5'], ['id' => 4]]);
        $grid = new FurniturePricingGrid();

        self::assertSame(30.0, $prices->price($grid, 3, null));
        self::assertSame(0.0, $prices->price($grid, 4, null));
        self::assertSame(30.0, $prices->forFurniture(['modelId' => 3])->price($grid, null, null));
    }

    private static function hardware(array $zone, ?array $handles = null): int
    {
        return self::line(FurniturePriceCalculator::fromConfigRows([], self::MODELS)->estimate(self::config($zone, $handles)), 'hardware');
    }

    private static function line(array $estimate, string $key): int
    {
        foreach ($estimate['lines'] as $line) {
            if ($line['key'] === $key) {
                return $line['amount'];
            }
        }
        self::fail('Missing line ' . $key);
    }

    private static function config(array $zone, ?array $handles = null): array
    {
        $config = [
            'dimensions' => ['width' => 1500, 'height' => 800, 'depth' => 400],
            'styling' => ['materialKey' => 'mdf_melamine', 'socle' => 'none'],
            'features' => ['doorType' => 'none'],
            'advancedZones' => $zone,
        ];
        return $handles === null ? $config : $config + ['handles' => $handles];
    }

    private static function leaf(?string $content = null, array $extra = []): array
    {
        return ['id' => 'z', 'type' => 'leaf', 'content' => $content ?? 'empty'] + $extra;
    }

    private static function split(array $children): array
    {
        return ['id' => 'root', 'type' => 'vertical', 'children' => $children];
    }
}
