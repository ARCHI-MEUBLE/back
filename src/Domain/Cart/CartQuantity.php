<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Lib\Validation\ValidationException;

final class CartQuantity
{
    public const MAX = 50;

    public static function forAdd(mixed $value): int
    {
        $quantity = self::parse($value ?? 1);
        if ($quantity < 1) {
            throw new ValidationException(sprintf('La quantité doit être comprise entre 1 et %d', self::MAX));
        }
        return $quantity;
    }

    public static function forUpdate(mixed $value): int
    {
        $quantity = self::parse($value);
        if ($quantity < 0) {
            throw new ValidationException(sprintf('La quantité doit être comprise entre 0 et %d', self::MAX));
        }
        return $quantity;
    }

    public static function capped(int $quantity): int
    {
        return min(self::MAX, $quantity);
    }

    private static function parse(mixed $value): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) && !(is_float($value) && floor($value) === $value)) {
            throw new ValidationException('Quantité invalide');
        }
        $quantity = (int) $value;
        if ($quantity > self::MAX) {
            throw new ValidationException(sprintf('La quantité doit être comprise entre 1 et %d', self::MAX));
        }
        return $quantity;
    }
}
