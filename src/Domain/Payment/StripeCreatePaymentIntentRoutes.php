<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Infrastructure\Stripe\StripeGateway;

final class StripeCreatePaymentIntentRoutes
{
    private const DESCRIPTIONS = [
        'deposit' => 'ArchiMeuble - Acompte de commande',
        'balance' => 'ArchiMeuble - Solde de commande',
        'full' => 'ArchiMeuble - Paiement intégral',
    ];

    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly CustomerRepository $customers,
        private readonly Connection $db,
        private readonly PaymentTargetResolver $targets,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('stripe/create-payment-intent', [new CustomerGuard()])->post(null, function (Request $r, Session $s): Response {
            $this->stripe->ensureConfigured();
            $customerId = (int) $s->customerId();
            [$order, $type] = $this->target($r->jsonOrEmpty(), $customerId);
            $intent = $this->stripe->createPaymentIntent([
                'amount' => PaymentAmountPolicy::cents(PaymentAmountPolicy::dueAmount($order, $type)),
                'currency' => PaymentAmountPolicy::CURRENCY,
                'customer' => $this->stripeCustomerId($customerId),
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => ['customer_id' => $customerId, 'order_id' => (int) $order['id'], 'payment_type' => $type],
                'description' => self::DESCRIPTIONS[$type] . ' ' . $order['order_number'],
            ]);
            $this->targets->bind((int) $order['id'], $type, $intent->id);
            return Response::json(['success' => true, 'clientSecret' => $intent->client_secret, 'paymentIntentId' => $intent->id, 'amount' => $intent->amount]);
        });
    }

    private function target(array $data, int $customerId): array
    {
        $requestedType = PaymentTargetResolver::validType($data['payment_type'] ?? null);
        $amount = isset($data['amount']) && is_numeric($data['amount']) ? PaymentAmountPolicy::cents($data['amount']) : null;
        $orderId = isset($data['order_id']) && is_numeric($data['order_id']) ? (int) $data['order_id'] : 0;
        if ($orderId > 0) {
            $order = $this->db->queryOne('SELECT * FROM orders WHERE id = ? AND customer_id = ?', [$orderId, $customerId]);
            if ($order === null) {
                throw new ForbiddenException('Accès interdit');
            }
            $type = $this->targets->typeFor($order, $requestedType, null);
            if ($type === null) {
                throw new DomainException('Aucun paiement en attente pour cette commande', 409);
            }
            return [$order, $type];
        }
        if ($amount === null || $amount <= 0) {
            throw new DomainException('Montant invalide');
        }
        $target = $this->targets->findByAmount($customerId, $amount);
        if ($target === null) {
            throw new DomainException('Le montant ne correspond à aucune commande en attente de paiement. Rechargez la page et réessayez.', 409);
        }
        return $target;
    }

    private function stripeCustomerId(int $customerId): string
    {
        $customer = (array) $this->customers->findRawById($customerId);
        $stripeCustomerId = $customer['stripe_customer_id'] ?? null;
        if (is_string($stripeCustomerId) && $stripeCustomerId !== '') {
            return $stripeCustomerId;
        }
        $stripeCustomer = $this->stripe->createCustomer([
            'email' => $customer['email'],
            'name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
            'metadata' => ['customer_id' => $customerId],
        ]);
        $this->customers->updateStripeCustomerId($customerId, $stripeCustomer->id);
        return $stripeCustomer->id;
    }
}
