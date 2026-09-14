<?php

declare(strict_types=1);

namespace App\Infrastructure\Stripe;

use App\Config\StripeSettings;
use App\Domain\Shared\DomainException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

final class StripeGateway
{
    public function __construct(private readonly StripeSettings $settings) {}

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        $this->ensureConfigured();
        return PaymentIntent::retrieve($id);
    }

    public function createPaymentIntent(array $params): PaymentIntent
    {
        $this->ensureConfigured();
        return PaymentIntent::create($params);
    }

    public function ensureConfigured(): void
    {
        if (!$this->settings->isConfigured()) {
            throw new DomainException('Clé Stripe non configurée', 500);
        }
        Stripe::setApiKey((string) $this->settings->secretKey);
    }
}
