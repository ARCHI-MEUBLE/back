<?php

declare(strict_types=1);

namespace App\Domain\Category;

final class CategoryImageUrl
{
    public static function absolute(?string $path, string $host): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $path = str_starts_with($path, '/') ? $path : '/' . $path;
        if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
            return $path;
        }
        return 'https://' . $host . $path;
    }

    public static function decorate(array $category, string $host): array
    {
        if (array_key_exists('image_url', $category)) {
            $category['image_url'] = self::absolute($category['image_url'], $host);
        }
        return $category;
    }
}
