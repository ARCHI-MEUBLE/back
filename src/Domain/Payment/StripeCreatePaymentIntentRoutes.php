<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Customer\CustomerRepository;
use App\Domain\Shared\DomainException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Infrastructure\Stripe\StripeGateway;

final class StripeCreatePaymentIntentRoutes
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly CustomerRepository $customers,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('stripe/create-payment-intent', [new CustomerGuard()])->post(null, function (Request $r, Session $s): Response {
            $this->stripe->ensureConfigured();
            $data = $r->jsonOrEmpty();
            if (!isset($data['amount']) || (float) $data['amount'] <= 0) {
                throw new DomainException('Montant invalide');
            }
            $amount = (int) ((float) $data['amount'] * 100);
            $currency = $data['currency'] ?? 'eur';
            $paymentType = $data['payment_type'] ?? 'full';
            $customerId = (int) $s->customerId();
            $customer = (array) $this->customers->findRawById($customerId);
            $stripeCustomerId = $customer['stripe_customer_id'] ?? null;
            if ($stripeCustomerId === null) {
                $stripeCustomer = $this->stripe->createCustomer([
                    'email' => $customer['email'],
                    'name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                    'metadata' => ['customer_id' => $customerId],
                ]);
                $stripeCustomerId = $stripeCustomer->id;
                $this->customers->updateStripeCustomerId($customerId, $stripeCustomerId);
            }
            $description = match ($paymentType) {
                'deposit' => 'ArchiMeuble - Acompte de commande',
                'balance' => 'ArchiMeuble - Solde de commande',
                default => 'ArchiMeuble - Paiement intégral',
            };
            $intent = $this->stripe->createPaymentIntent([
                'amount' => $amount,
                'currency' => $currency,
                'customer' => $stripeCustomerId,
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => ['customer_id' => $customerId, 'payment_type' => $paymentType],
                'description' => $description,
            ]);
            return Response::json(['success' => true, 'clientSecret' => $intent->client_secret, 'paymentIntentId' => $intent->id, 'amount' => $intent->amount]);
        });
    }
}
