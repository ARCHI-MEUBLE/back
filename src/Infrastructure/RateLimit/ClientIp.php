<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

final class ClientIp
{
    public const CLIENT_IP_HEADER = 'HTTP_X_ARCHIMEUBLE_CLIENT_IP';
    public const PROXY_SECRET_HEADER = 'HTTP_X_ARCHIMEUBLE_PROXY_SECRET';
    public const MIN_SECRET_LENGTH = 32;

    private static ?string $frontProxySecret = null;

    public static function trustFrontProxy(?string $secret): void
    {
        self::$frontProxySecret = $secret !== null && strlen($secret) >= self::MIN_SECRET_LENGTH ? $secret : null;
    }

    public static function from(array $server, ?string $frontProxySecret = null): string
    {
        $fromFront = self::fromTrustedFront($server, $frontProxySecret ?? self::$frontProxySecret);
        if ($fromFront !== null) {
            return $fromFront;
        }

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

    private static function fromTrustedFront(array $server, ?string $secret): ?string
    {
        if ($secret === null || strlen($secret) < self::MIN_SECRET_LENGTH) {
            return null;
        }
        $given = $server[self::PROXY_SECRET_HEADER] ?? null;
        $ip = $server[self::CLIENT_IP_HEADER] ?? null;
        if (!is_string($given) || !is_string($ip) || !hash_equals($secret, $given)) {
            return null;
        }
        $ip = trim($ip);
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }
}
