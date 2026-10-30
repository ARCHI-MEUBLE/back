<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

final class ShareCode
{
    public const PATTERN = '/^[a-km-np-zA-HJ-NP-Z2-9]{8}$/';

    private const ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LENGTH = 8;

    public static function generate(): string
    {
        $code = '';
        $last = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $last)];
        }
        return $code;
    }

    public static function isValid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1;
    }
}
