<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

use App\Domain\Shared\DomainException;

final class Throttle
{
    public const MESSAGE = 'Trop de requêtes. Merci de réessayer plus tard.';

    public function __construct(private readonly RateLimiter $limiter) {}

    public function consume(string $identifier, string $type, int $max, int $windowMinutes, string $message = self::MESSAGE): void
    {
        $this->assertAllowed($identifier, $type, $max, $windowMinutes, $message);
        $this->limiter->hit($identifier, $type, false, $max, $windowMinutes);
    }

    public function assertAllowed(string $identifier, string $type, int $max, int $windowMinutes, string $message = self::MESSAGE): void
    {
        if (!$this->limiter->check($identifier, $type, $max, $windowMinutes)->allowed) {
            throw new DomainException($message, 429);
        }
    }

    public function fail(string $identifier, string $type, int $max, int $windowMinutes): void
    {
        $this->limiter->hit($identifier, $type, false, $max, $windowMinutes);
    }

    public function reset(string $identifier, string $type): void
    {
        $this->limiter->resetAttempts($identifier, $type);
    }
}
