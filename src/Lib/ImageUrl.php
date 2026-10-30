<?php

declare(strict_types=1);

namespace App\Lib;

final class ImageUrl
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

    public static function decorate(array $row, string $host, array $keys = ['image_url']): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = self::absolute($row[$key], $host);
            }
        }
        return $row;
    }
}
