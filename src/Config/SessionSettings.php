<?php

declare(strict_types=1);

namespace App\Config;

final class SessionSettings
{
    public const COOKIE_NAME = 'ARCHIMEUBLE_SESSID';

    public function __construct(
        public readonly ?bool $secure,
        public readonly int $regenerationInterval,
    ) {}

    public static function fromEnv(Env $env): self
    {
        $secure = $env->string('SESSION_SECURE');
        return new self(
            $secure === null ? null : in_array(strtolower($secure), ['1', 'true', 'yes', 'on'], true),
            $env->int('SESSION_REGENERATION_SECONDS', 1800),
        );
    }

    public function secureFor(string $host): bool
    {
        if ($this->secure !== null) {
            return $this->secure;
        }
        return !str_contains($host, 'localhost') && !str_contains($host, '127.0.0.1');
    }
}
