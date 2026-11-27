<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Shared\DomainException;

final class FurnitureCostLines
{
    private const DOORS = ['door', 'door_right', 'door_double', 'sliding_door', 'mirror_door', 'mirror_door_right', 'push_door', 'push_door_right'];
    private const GLASS_DOORS = ['mirror_door', 'mirror_door_right'];
    private const DRAWERS = ['drawer', 'push_drawer'];
    private const MAX_DEPTH = 12;
    private const MAX_ZONES = 400;

    private array $amounts = [];
    private int $zoneCount = 0;

    public function __construct(
        private readonly FurniturePricingGrid $grid,
        private readonly FurnitureMaterials $materials,
        private readonly float $depth,
        private readonly HandlePrices $handles = new HandlePrices(),
    ) {}

    public function add(string $line, float $amount): void
    {
        $this->amounts[$line] = ($this->amounts[$line] ?? 0.0) + $amount;
    }

    public function amount(string $line): float
    {
        return $this->amounts[$line] ?? 0.0;
    }

    public function carcass(float $width, float $height): void
    {
        $structure = 2 * $height * $this->depth + 2 * $width * $this->depth;
        $this->add('panels', $structure / 1_000_000 * $this->materials->part('structure') + $this->back($width, $height));
    }

    public function zone(array $zone, float $width, float $height, int $level = 0): void
    {
        if ($level > self::MAX_DEPTH || ++$this->zoneCount > self::MAX_ZONES) {
            throw new DomainException('Configuration trop complexe');
        }
        $content = is_string($zone['content'] ?? null) ? $zone['content'] : null;
        $isLeaf = ($zone['type'] ?? 'leaf') === 'leaf';
        if ($isLeaf && ($zone['isOpenSpace'] ?? false) === true) {
            $this->add('panels', -$this->back($width, $height));
            return;
        }
        $doorContent = is_string($zone['doorContent'] ?? null) ? $zone['doorContent'] : $content;
        if ($doorContent !== null && in_array($doorContent, self::DOORS, true)) {
            $handle = $doorContent === SlidingDoor::CONTENT ? $this->handles->flushPrice($zone) : $this->handles->price($this->grid, $zone);
            $this->door($doorContent, $handle, $width, $height, $this->materials->facade($zone, 'doors'));
        }
        $children = is_array($zone['children'] ?? null) ? array_values(array_filter($zone['children'], 'is_array')) : [];
        if ($isLeaf || $children === []) {
            $this->leaf($zone, $content, $width, $height);
            return;
        }
        $horizontal = $zone['type'] === 'horizontal';
        $count = count($children);
        $drawerStack = $horizontal && $count >= 2 && array_reduce($children, fn(bool $all, array $c): bool => $all && in_array($c['content'] ?? null, self::DRAWERS, true), true);
        if (!$drawerStack) {
            $separator = $horizontal ? $width * $this->depth : $height * $this->depth;
            $this->add('panels', ($count - 1) * $separator / 1_000_000 * $this->materials->part('shelves'));
        }
        foreach (ZoneRatios::of($zone, $count) as $index => $ratio) {
            $this->zone($children[$index], $horizontal ? $width : $width * $ratio, $horizontal ? $height * $ratio : $height, $level + 1);
        }
    }

    public function door(string $type, float $handle, float $width, float $height, float $materialPrice): void
    {
        $sliding = $type === SlidingDoor::CONTENT;
        $leaves = $sliding ? SlidingDoor::panels($width) : ($type === 'door_double' ? 2 : 1);
        $surface = $sliding ? SlidingDoor::panelSurface($width, $height) : $width * $height / 1_000_000;
        $glass = in_array($type, self::GLASS_DOORS, true) ? $surface * $this->grid->get('fronts', 'glass', 'supplement_per_m2') : 0.0;
        $this->add('fronts', $leaves * $this->grid->get('fronts', 'door', 'fixed_price') + $this->facade($surface, $materialPrice) + $glass);
        $this->add('hardware', $leaves * (str_starts_with($type, 'push_') ? $this->grid->get('fronts', 'push', 'supplement') : $handle) + ($sliding ? SlidingDoor::rails($this->grid, $width) : 0.0));
        $this->add('panels', $sliding ? SlidingDoor::grooves($this->grid, $width) : 0.0);
    }

    public function base(string $socle, float $width): void
    {
        if ($socle === 'metal') {
            $spacing = max(100.0, $this->grid->get('bases', 'metal', 'foot_spacing_mm'));
            $pairs = max((int) $this->grid->get('bases', 'metal', 'min_pairs'), (int) ceil($width / $spacing) + 1);
            $this->add('hardware', 2 * $pairs * $this->grid->get('bases', 'metal', 'price_per_foot'));
        } elseif ($socle === 'wood') {
            $this->add('hardware', $width / 1000 * $this->grid->get('bases', 'wood', 'price_per_linear_meter'));
        }
    }

    public static function hasLeafDoor(array $zone, int $level = 0): bool
    {
        if (($zone['type'] ?? 'leaf') === 'leaf' && in_array($zone['content'] ?? null, self::DOORS, true)) {
            return true;
        }
        foreach ($level < self::MAX_DEPTH && is_array($zone['children'] ?? null) ? $zone['children'] : [] as $child) {
            if (is_array($child) && self::hasLeafDoor($child, $level + 1)) {
                return true;
            }
        }
        return false;
    }

    private function leaf(array $zone, ?string $content, float $width, float $height): void
    {
        if (($zone['noBack'] ?? false) === true) {
            $this->add('panels', -$this->back($width, $height));
        }
        if ($content !== null && in_array($content, self::DRAWERS, true)) {
            $facade = $this->facade($width * $height / 1_000_000, $this->materials->facade($zone, 'drawers'));
            $this->add('fronts', $this->grid->get('fronts', 'drawer', 'fixed_price') + $facade);
            $this->add('hardware', $content === 'push_drawer' ? $this->grid->get('fronts', 'push', 'supplement') : $this->handles->price($this->grid, $zone));
        }
        if ($content === 'shelf') {
            $this->add('panels', $width * $this->depth / 1_000_000 * $this->materials->part('shelves'));
        }
        if ($content === 'dressing' || ($zone['hasDressing'] ?? false) === true) {
            $this->add('hardware', $width / 1000 * $this->grid->get('options', 'dressing_rod', 'price_per_linear_meter'));
        }
        if (($zone['hasLight'] ?? false) === true) {
            $this->add('hardware', $width / 1000 * $this->grid->get('options', 'led', 'price_per_linear_meter'));
        }
        if (($zone['hasCableHole'] ?? false) === true) {
            $this->add('hardware', $this->grid->get('options', 'cable_hole', 'fixed_price'));
        }
    }

    private function back(float $width, float $height): float
    {
        return $width * $height / 1_000_000 * $this->grid->get('panels', 'back', 'surface_factor') * $this->materials->part('back');
    }

    private function facade(float $surface, float $materialPrice): float
    {
        return $surface * $materialPrice * $this->grid->get('fronts', 'facade', 'material_factor');
    }
}
