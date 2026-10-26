<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

final class CalendlyWebhookSignature
{
    private const TOLERANCE_SECONDS = 300;

    public function __construct(private readonly ?string $signingKey) {}

    public function isConfigured(): bool
    {
        return $this->signingKey !== null && $this->signingKey !== '';
    }

    public function verify(string $header, string $body, ?int $now = null): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }
        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';
        if (!ctype_digit($timestamp) || $signature === '' || abs(($now ?? time()) - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $timestamp . '.' . $body, (string) $this->signingKey), $signature);
    }

    public function sign(string $body, int $timestamp): string
    {
        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, (string) $this->signingKey);
    }
}
