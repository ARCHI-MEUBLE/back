<?php

declare(strict_types=1);

namespace App\Domain\Realisation;

final class RealisationPresenter
{
    public static function withImages(array $realisation, array $images): array
    {
        $mainImageUrl = $realisation['image_url'] ?? null;
        if (is_string($mainImageUrl) && $mainImageUrl !== '' && !self::containsUrl($images, $mainImageUrl)) {
            array_unshift($images, [
                'id' => 0,
                'realisation_id' => $realisation['id'],
                'image_url' => $mainImageUrl,
                'legende' => null,
                'ordre' => -1,
            ]);
        }
        $realisation['images'] = $images;
        if ($images !== []) {
            $realisation['image_url'] = $images[0]['image_url'];
        }
        return $realisation;
    }

    private static function containsUrl(array $images, string $url): bool
    {
        foreach ($images as $image) {
            if (($image['image_url'] ?? null) === $url) {
                return true;
            }
        }
        return false;
    }
}
