<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Infrastructure\Stripe\StripeGateway;
use App\Infrastructure\Stripe\StripeObjectReader;

final class OrderPaymentIntentRoutes
{
    public function __construct(
        private readonly Connection $db,
        private readonly StripeGateway $stripe,
        private readonly PaymentTargetResolver $targets,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('orders/payment-intent', [new CustomerGuard()])->post(null, function (Request $r, Session $s): Response {
            $orderId = (int) ($r->queryString('id') ?? 0);
            if ($orderId === 0) {
                throw new DomainException('ID de commande manquant');
            }
            $data = $r->jsonOrEmpty();
            if (!isset($data['payment_intent_id'])) {
                throw new DomainException('Payment intent ID manquant');
            }
            $order = $this->db->queryOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
            if ($order === null || (int) $order['customer_id'] !== (int) $s->customerId()) {
                throw new ForbiddenException('Accès interdit');
            }
            $intentId = trim((string) $data['payment_intent_id']);
            if (preg_match('/^pi_[A-Za-z0-9_]+$/', $intentId) !== 1) {
                throw new DomainException('Payment intent ID invalide');
            }
            $intent = $this->stripe->retrievePaymentIntent($intentId);
            $metadata = StripeObjectReader::metadataArray($intent);
            if ((int) ($metadata['customer_id'] ?? 0) !== (int) $s->customerId() || (isset($metadata['order_id']) && (int) $metadata['order_id'] !== $orderId)) {
                throw new ForbiddenException('Ce paiement ne vous appartient pas ou concerne une autre commande');
            }
            $requested = PaymentTargetResolver::validType($data['payment_type'] ?? null) ?? PaymentTargetResolver::validType($metadata['payment_type'] ?? null);
            $type = strtolower((string) $intent->currency) === PaymentAmountPolicy::CURRENCY
                ? $this->targets->typeFor($order, $requested, StripeObjectReader::int($intent, 'amount'))
                : null;
            if ($type === null) {
                throw new DomainException('Le montant du paiement ne correspond pas au montant dû pour cette commande', 409);
            }
            $this->targets->bind($orderId, $type, $intentId);
            return Response::json(['success' => true]);
        });
    }
}
