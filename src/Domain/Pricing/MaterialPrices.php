<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final class MaterialPrices
{
    public const PARTS = ['structure', 'back', 'shelves', 'doors', 'drawers'];

    private const MAX_IDS = 500;
    private const MAX_DEPTH = 12;
    private const ACCENTS = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e',
        'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
        'ò' => 'o', 'õ' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
    ];

    public function __construct(
        private readonly array $colors = [],
        private readonly array $materials = [],
    ) {}

    public static function fromRows(array $rows): self
    {
        $colors = [];
        $materials = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $price = is_numeric($row['price_per_m2'] ?? null) ? (float) $row['price_per_m2'] : null;
            $id = self::colorId($row['color_id'] ?? null);
            if ($id !== null) {
                $colors[$id] = $price;
            } elseif ($price !== null && is_string($row['material'] ?? null)) {
                $materials[self::normalize($row['material'])] ??= $price;
            }
        }
        return new self($colors, $materials);
    }

    public static function colorIds(array $config): array
    {
        $styling = is_array($config['styling'] ?? null) ? $config['styling'] : [];
        $ids = [$styling['colorId'] ?? null, $styling['selectedColorId'] ?? null];
        foreach (self::PARTS as $part) {
            $ids[] = self::partColor($config, $part);
        }
        $zone = $config['advancedZones'] ?? $config['rootZone'] ?? null;
        if (is_array($zone)) {
            self::zoneColorIds($zone, $ids, 0);
        }
        $valid = array_filter(array_map(self::colorId(...), $ids), static fn(?int $id): bool => $id !== null);
        return array_slice(array_values(array_unique($valid)), 0, self::MAX_IDS);
    }

    public static function colorId(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^\d{1,9}$/', $value) === 1) {
            $value = (int) $value;
        }
        return is_int($value) && $value > 0 ? $value : null;
    }

    public static function normalize(string $name): string
    {
        $text = strtr(mb_strtolower(trim($name)), self::ACCENTS);
        return trim((string) preg_replace(['/\p{Mn}+/u', '/[\s_]+/u'], ['', ' '], $text));
    }

    public function forConfiguration(array $config, float $default): FurnitureMaterials
    {
        $styling = is_array($config['styling'] ?? null) ? $config['styling'] : [];
        $main = $this->colorPrice($styling['colorId'] ?? null, $default)
            ?? $this->colorPrice($styling['selectedColorId'] ?? null, $default)
            ?? $this->byName($styling)
            ?? $default;
        $multi = ($config['useMultiColor'] ?? false) === true;
        $parts = [];
        foreach (self::PARTS as $part) {
            $parts[$part] = $multi ? ($this->colorPrice(self::partColor($config, $part), $default) ?? $main) : $main;
        }
        $colors = array_map(static fn(?float $price): float => $price ?? $default, $this->colors);
        return new FurnitureMaterials($parts, $colors);
    }

    private function colorPrice(mixed $value, float $default): ?float
    {
        $id = self::colorId($value);
        if ($id === null || !array_key_exists($id, $this->colors)) {
            return null;
        }
        return $this->colors[$id] ?? $default;
    }

    private function byName(array $styling): ?float
    {
        $texts = [];
        foreach (['materialKey', 'materialLabel', 'finish'] as $key) {
            if (is_string($styling[$key] ?? null) && trim($styling[$key]) !== '') {
                $texts[] = self::normalize($styling[$key]);
            }
        }
        foreach ($texts as $text) {
            if (isset($this->materials[$text])) {
                return (float) $this->materials[$text];
            }
        }
        foreach (array_filter(array_map(self::legacyKey(...), $texts)) as $legacy) {
            foreach ($this->materials as $name => $price) {
                if (self::legacyKey((string) $name) === $legacy) {
                    return (float) $price;
                }
            }
        }
        return null;
    }

    private static function legacyKey(string $name): ?string
    {
        return match (true) {
            str_contains($name, 'aggl') => 'agglomere',
            str_contains($name, 'bois') => 'plaque_bois',
            str_contains($name, 'mdf'), str_contains($name, 'melamin') => 'mdf_melamine',
            default => null,
        };
    }

    private static function partColor(array $config, string $part): mixed
    {
        $colors = is_array($config['componentColors'] ?? null) ? $config['componentColors'] : [];
        return is_array($colors[$part] ?? null) ? ($colors[$part]['colorId'] ?? null) : null;
    }

    private static function zoneColorIds(array $zone, array &$ids, int $level): void
    {
        if ($level > self::MAX_DEPTH || count($ids) > self::MAX_IDS) {
            return;
        }
        $ids[] = is_array($zone['zoneColor'] ?? null) ? ($zone['zoneColor']['colorId'] ?? null) : null;
        foreach (is_array($zone['children'] ?? null) ? $zone['children'] : [] as $child) {
            if (is_array($child)) {
                self::zoneColorIds($child, $ids, $level + 1);
            }
        }
    }
}
