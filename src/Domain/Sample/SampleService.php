<?php

declare(strict_types=1);

namespace App\Domain\Sample;

final class SampleService
{
    public function __construct(
        private readonly SampleTypeRepository $types,
        private readonly SampleColorRepository $colors,
    ) {}

    public function groupedByMaterialForCustomers(): array
    {
        $grouped = [];
        foreach ($this->types->all() as $type) {
            if (!$type['active']) {
                continue;
            }
            $activeColors = array_values(array_filter($this->colors->forType((int) $type['id']), static fn(array $c): bool => (bool) $c['active']));
            $grouped[$type['material']][] = self::typeShape($type, $activeColors);
        }
        return $grouped;
    }

    public function groupedForAdmin(): array
    {
        return array_map(
            fn(array $type): array => self::typeShape($type, $this->colors->forType((int) $type['id'])),
            $this->types->all(),
        );
    }

    private static function typeShape(array $type, array $colors): array
    {
        return [
            'id' => $type['id'], 'name' => $type['name'], 'material' => $type['material'],
            'description' => $type['description'], 'active' => $type['active'], 'position' => $type['position'],
            'colors' => $colors,
        ];
    }
}
