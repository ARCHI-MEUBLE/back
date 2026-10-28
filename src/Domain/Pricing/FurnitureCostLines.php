<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Shared\DomainException;

final class FurnitureCostLines
{
    private const DOORS = ['door', 'door_right', 'door_double', 'mirror_door', 'mirror_door_right', 'push_door', 'push_door_right'];
    private const DRAWERS = ['drawer', 'push_drawer'];
    private const MAX_DEPTH = 12;
    private const MAX_ZONES = 400;

    private array $amounts = [];
    private int $zoneCount = 0;

    public function __construct(
        private readonly FurniturePricingGrid $grid,
        private readonly float $materialPrice,
        private readonly float $depth,
    ) {}

    public function add(string $line, float $amount): void
    {
        $this->amounts[$line] = ($this->amounts[$line] ?? 0.0) + $amount;
    }

    public function amount(string $line): float
    {
        return $this->amounts[$line] ?? 0.0;
    }

    public function zone(array $zone, float $width, float $height, int $level = 0): void
    {
        if ($level > self::MAX_DEPTH || ++$this->zoneCount > self::MAX_ZONES) {
            throw new DomainException('Configuration trop complexe');
        }
        $content = is_string($zone['content'] ?? null) ? $zone['content'] : null;
        $isLeaf = ($zone['type'] ?? 'leaf') === 'leaf';
        $doorContent = is_string($zone['doorContent'] ?? null) ? $zone['doorContent'] : ($isLeaf ? $content : null);
        $handle = is_string($zone['handleType'] ?? null) ? $zone['handleType'] : null;
        if ($doorContent !== null && in_array($doorContent, self::DOORS, true)) {
            $this->door($doorContent, $handle, $width, $height);
        }
        $children = is_array($zone['children'] ?? null) ? array_values(array_filter($zone['children'], 'is_array')) : [];
        if ($isLeaf || $children === []) {
            $this->leaf($zone, $content, $handle, $width, $height);
            return;
        }
        $horizontal = $zone['type'] === 'horizontal';
        $count = count($children);
        $drawerStack = $horizontal && $count >= 2 && array_reduce($children, fn(bool $all, array $c): bool => $all && in_array($c['content'] ?? null, self::DRAWERS, true), true);
        if (!$drawerStack) {
            $separator = $horizontal ? $width * $this->depth : $height * $this->depth;
            $this->add('panels', ($count - 1) * $separator / 1_000_000 * $this->materialPrice);
        }
        foreach ($children as $index => $child) {
            $ratio = self::ratio($zone, $index, $count);
            $this->zone($child, $horizontal ? $width : $width * $ratio, $horizontal ? $height * $ratio : $height, $level + 1);
        }
    }

    public function door(string $type, ?string $handle, float $width, float $height): void
    {
        $leaves = $type === 'door_double' ? 2 : 1;
        $this->add('fronts', $leaves * $this->grid->get('fronts', 'door', 'fixed_price') + $this->facade($width, $height));
        $this->add('hardware', str_starts_with($type, 'push_')
            ? $leaves * $this->grid->get('fronts', 'push', 'supplement')
            : $leaves * $this->handle($handle));
    }

    public function base(string $socle, float $width): void
    {
        if ($socle === 'metal') {
            $pairs = max(2, (int) ceil($width / $this->grid->get('bases', 'metal', 'foot_spacing_mm')) + 1);
            $this->add('hardware', 2 * $pairs * $this->grid->get('bases', 'metal', 'price_per_foot'));
        } elseif ($socle === 'wood') {
            $this->add('hardware', $width / 1000 * $this->grid->get('bases', 'wood', 'price_per_linear_meter'));
        }
    }

    private function leaf(array $zone, ?string $content, ?string $handle, float $width, float $height): void
    {
        if ($content !== null && in_array($content, self::DRAWERS, true)) {
            $this->add('fronts', $this->grid->get('fronts', 'drawer', 'fixed_price') + $this->facade($width, $height));
            $this->add('hardware', $content === 'push_drawer' ? $this->grid->get('fronts', 'push', 'supplement') : $this->handle($handle));
        }
        if ($content === 'shelf') {
            $this->add('panels', $width * $this->depth / 1_000_000 * $this->materialPrice);
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

    private function facade(float $width, float $height): float
    {
        return $width * $height / 1_000_000 * $this->materialPrice * $this->grid->get('fronts', 'facade', 'material_factor');
    }

    private function handle(?string $type): float
    {
        return $type !== null && $this->grid->has('handles', $type) ? $this->grid->get('handles', $type, 'price_per_unit') : 0.0;
    }

    private static function ratio(array $zone, int $index, int $count): float
    {
        $ratios = $zone['splitRatios'] ?? null;
        if (is_array($ratios) && count($ratios) === $count && is_numeric($ratios[$index] ?? null)) {
            return max(0.0, (float) $ratios[$index] / 100);
        }
        if ($count === 2 && is_numeric($zone['splitRatio'] ?? null)) {
            $first = (float) $zone['splitRatio'] / 100;
            return max(0.0, $index === 0 ? $first : 1 - $first);
        }
        return 1 / $count;
    }
}
