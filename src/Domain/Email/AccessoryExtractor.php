<?php

declare(strict_types=1);

namespace App\Domain\Email;

final class AccessoryExtractor
{
    private const LABELS = [
        'shelf' => 'Étagère', 'drawer' => 'Tiroir', 'hanging_rod' => 'Barre de penderie', 'door' => 'Porte',
        'basket' => 'Panier', 'tray' => 'Plateau', 'divider' => 'Séparateur', 'shoe_rack' => 'Range-chaussures',
        'hanger' => 'Portant', 'dressing' => 'Aménagement penderie', 'empty' => 'Niche vide',
    ];

    public static function extract(mixed $zone, string $prefix = ''): array
    {
        $accessories = [];
        if (!is_array($zone) || $zone === []) {
            return $accessories;
        }
        $zoneName = $zone['name'] ?? ($prefix === '' ? 'Meuble' : 'Zone');
        $currentPrefix = $prefix !== '' ? "{$prefix} > {$zoneName}" : $zoneName;
        if (($zone['type'] ?? null) === 'leaf') {
            $content = $zone['content'] ?? 'empty';
            if ($content !== 'empty') {
                $accessories[] = "{$currentPrefix} : " . self::label((string) $content);
            }
        }
        if (is_array($zone['accessories'] ?? null)) {
            foreach ($zone['accessories'] as $acc) {
                $accessories[] = "{$currentPrefix} : " . self::label((string) ($acc['type'] ?? 'inconnu'));
            }
        }
        if (is_array($zone['children'] ?? null)) {
            foreach ($zone['children'] as $idx => $child) {
                $childName = $child['name'] ?? ('Section ' . ($idx + 1));
                $accessories = array_merge($accessories, self::extract($child, "{$currentPrefix} > {$childName}"));
            }
        }
        return $accessories;
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
