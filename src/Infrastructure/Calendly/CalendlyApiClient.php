<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendly;

final class CalendlyApiClient
{
    private const BASE_URL = 'https://api.calendly.com/';

    public function __construct(private readonly string $token) {}

    public static function isApiUrl(string $url): bool
    {
        return str_starts_with($url, self::BASE_URL)
            && parse_url($url, PHP_URL_HOST) === 'api.calendly.com'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PORT) === null;
    }

    public function get(string $url): array
    {
        if (!self::isApiUrl($url)) {
            return [400, ''];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token, 'Content-Type: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return [$status, is_string($body) ? $body : ''];
    }
}
