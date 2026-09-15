<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\Stripe\StripeGateway;

final class PaymentLinkCreateIntentRoutes
{
    public function __construct(
        private readonly PaymentLinkRepository $links,
        private readonly StripeGateway $stripe,
        private readonly Connection $db,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('payment-link/create-payment-intent', errorStyle: ErrorStyle::Success)->post(null, fn(Request $r): Response => $this->create($r));
    }

    private function create(Request $r): Response
    {
        $data = $r->jsonOrEmpty();
        if (!isset($data['token'])) {
            throw new DomainException('Token manquant');
        }
        $token = trim((string) $data['token']);
        $validation = $this->links->validateLink($token);
        if ($validation['valid'] !== true) {
            throw new DomainException($validation['message']);
        }
        $orderData = $this->links->getOrderItemsByToken($token);
        if ($orderData === null) {
            throw new NotFoundException('Commande introuvable');
        }
        $order = $orderData['order'];
        $amount = (float) $order['amount'];
        $paymentType = $order['payment_type'] ?? 'full';
        $this->stripe->ensureConfigured();
        $customer = $this->db->queryOne('SELECT c.*, o.customer_id FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.id = ?', [$order['order_id']]);
        if ($customer === null) {
            throw new DomainException('Client introuvable', 500);
        }
        $stripeCustomerId = $customer['stripe_customer_id'] ?? null;
        if ($stripeCustomerId === null) {
            $stripeCustomer = $this->stripe->createCustomer([
                'email' => $customer['email'],
                'name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
                'phone' => $customer['phone'] ?? null,
                'metadata' => ['customer_id' => $customer['customer_id'], 'order_id' => $order['order_id'], 'payment_link_token' => $token],
            ]);
            $stripeCustomerId = $stripeCustomer->id;
            $this->db->execute('UPDATE customers SET stripe_customer_id = ? WHERE id = ?', [$stripeCustomerId, $customer['customer_id']]);
        }
        $intent = $this->stripe->createPaymentIntent([
            'amount' => (int) ($amount * 100),
            'currency' => 'eur',
            'customer' => $stripeCustomerId,
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => ['order_id' => $order['order_id'], 'order_number' => $order['order_number'], 'customer_id' => $customer['customer_id'], 'payment_link_token' => $token, 'payment_type' => $paymentType, 'source' => 'payment_link'],
            'description' => 'ArchiMeuble - Commande ' . $order['order_number'] . ' - Paiement ' . $paymentType,
        ]);
        $this->db->execute(
            'INSERT INTO stripe_payment_intents (payment_intent_id, order_id, customer_id, amount, currency, status, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
            [$intent->id, $order['order_id'], $customer['customer_id'], $intent->amount, $intent->currency, $intent->status, json_encode($intent->metadata->toArray())],
        );
        $column = match ($paymentType) {
            'deposit' => 'deposit_stripe_intent_id',
            'balance' => 'balance_stripe_intent_id',
            default => 'stripe_payment_intent_id',
        };
        $this->db->execute("UPDATE orders SET {$column} = ? WHERE id = ?", [$intent->id, $order['order_id']]);
        return Response::json(['success' => true, 'data' => ['clientSecret' => $intent->client_secret, 'paymentIntentId' => $intent->id, 'amount' => $intent->amount, 'order_number' => $order['order_number']]]);
    }
}
