<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendly;

final class CalendlyApiClient
{
    public function __construct(private readonly string $token) {}

    public function get(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->token, 'Content-Type: application/json']);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return [$status, is_string($body) ? $body : ''];
    }
}
