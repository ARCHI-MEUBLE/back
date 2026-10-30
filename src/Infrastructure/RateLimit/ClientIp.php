<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

final class ClientIp
{
    public static function from(array $server): string
    {
        $remote = is_string($server['REMOTE_ADDR'] ?? null) ? trim($server['REMOTE_ADDR']) : '';
        $forwarded = is_string($server['HTTP_X_FORWARDED_FOR'] ?? null) ? $server['HTTP_X_FORWARDED_FOR'] : '';
        if ($forwarded !== '' && !self::isPublic($remote)) {
            foreach (array_reverse(array_map('trim', explode(',', $forwarded))) as $hop) {
                if (self::isPublic($hop)) {
                    return $hop;
                }
            }
        }
        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '0.0.0.0';
    }

    public static function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        $long = ip2long($ip);
        return $long === false || ($long & 0xFFC00000) !== (ip2long('100.64.0.0') & 0xFFC00000);
    }
}
