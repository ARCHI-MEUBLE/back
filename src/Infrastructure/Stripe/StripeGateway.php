<?php

declare(strict_types=1);

namespace App\Infrastructure\Stripe;

use App\Config\StripeSettings;
use App\Domain\Shared\DomainException;
use Stripe\Customer;
use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

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

    public function createCustomer(array $params): Customer
    {
        $this->ensureConfigured();
        return Customer::create($params);
    }

    public function hasUsableWebhookSecret(): bool
    {
        $secret = $this->settings->webhookSecret;
        return $secret !== null && !in_array($secret, ['whsec_YOUR_WEBHOOK_SECRET_HERE', 'whsec_test_local_dev'], true);
    }

    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        $this->ensureConfigured();
        return Webhook::constructEvent($payload, $signature, (string) $this->settings->webhookSecret);
    }

    public function ensureConfigured(): void
    {
        if (!$this->settings->isConfigured()) {
            throw new DomainException('Clé Stripe non configurée', 500);
        }
        Stripe::setApiKey((string) $this->settings->secretKey);
    }
}
