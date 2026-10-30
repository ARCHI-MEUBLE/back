<?php

declare(strict_types=1);

namespace App\Config;

final class StripeSettings
{
    public function __construct(
        public readonly ?string $secretKey,
        public readonly ?string $publishableKey,
        public readonly ?string $webhookSecret,
    ) {}

    public static function fromEnv(Env $env): self
    {
        return new self($env->string('STRIPE_SECRET_KEY'), $env->string('STRIPE_PUBLISHABLE_KEY'), $env->string('STRIPE_WEBHOOK_SECRET'));
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== null && $this->secretKey !== 'sk_test_YOUR_SECRET_KEY_HERE';
    }
}
