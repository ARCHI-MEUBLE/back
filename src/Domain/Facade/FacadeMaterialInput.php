<?php

declare(strict_types=1);

namespace App\Domain\Facade;

use App\Domain\Shared\DomainException;

final class FacadeMaterialInput
{
    public static function colorAndTexture(array $data): array
    {
        $colorHex = (string) ($data['color_hex'] ?? '');
        $textureUrl = (string) ($data['texture_url'] ?? '');
        if ($colorHex === '' && $textureUrl === '') {
            throw new DomainException('Fournir une couleur (hex) OU une image de texture');
        }
        return $textureUrl !== '' ? ['#FFFFFF', $textureUrl] : [$colorHex, ''];
    }

    public static function isActive(array $data, string $key = 'is_active'): bool
    {
        return (isset($data[$key]) && $data[$key] !== '') ? filter_var($data[$key], FILTER_VALIDATE_BOOLEAN) : true;
    }
}
