<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

final class RateLimitDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $remaining,
        public readonly ?int $retryAfterSeconds,
        public readonly ?string $message,
    ) {}

    public static function allow(int $remaining): self
    {
        return new self(true, $remaining, null, null);
    }

    public static function deny(int $retryAfterSeconds, string $message): self
    {
        return new self(false, 0, $retryAfterSeconds, $message);
    }
}
