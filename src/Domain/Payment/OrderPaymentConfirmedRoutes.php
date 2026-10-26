<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Domain\Shared\NotFoundException;
use App\Http\Guard\CustomerGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Http\Session;
use App\Infrastructure\Stripe\StripeGateway;
use App\Infrastructure\Stripe\StripeObjectReader;

final class OrderPaymentConfirmedRoutes
{
    public function __construct(
        private readonly Connection $db,
        private readonly StripeGateway $stripe,
        private readonly PaymentConfirmationService $confirmation,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('orders/payment-confirmed', [new CustomerGuard()])->post(null, function (Request $r, Session $s): Response {
            $orderId = (int) ($r->queryString('id') ?? 0);
            if ($orderId === 0) {
                throw new DomainException('ID de commande manquant');
            }
            $data = $r->jsonOrEmpty();
            if (!isset($data['payment_intent_id'])) {
                throw new DomainException('Données manquantes');
            }
            $order = $this->db->queryOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
            if ($order === null) {
                throw new NotFoundException('Commande non trouvée');
            }
            if ((int) $order['customer_id'] !== (int) $s->customerId()) {
                throw new ForbiddenException('Accès interdit');
            }
            $intentId = trim((string) $data['payment_intent_id']);
            $bound = [$order['stripe_payment_intent_id'] ?? null, $order['deposit_stripe_intent_id'] ?? null, $order['balance_stripe_intent_id'] ?? null];
            if ($intentId === '' || !in_array($intentId, $bound, true)) {
                throw new ForbiddenException("Ce paiement n'est pas associé à cette commande");
            }
            $intent = $this->stripe->retrievePaymentIntent($intentId);
            if ($intent->status !== 'succeeded') {
                return Response::json(['success' => false, 'message' => 'Paiement non confirmé par Stripe', 'status' => $intent->status], 409);
            }
            $result = $this->confirmation->confirm($intentId, StripeObjectReader::metadataArray($intent), StripeObjectReader::amountReceived($intent), (string) $intent->currency);
            if (!$result->isPaid()) {
                return Response::json(['success' => false, 'message' => 'Le montant payé ne correspond pas à la commande. Notre équipe a été prévenue.'], 409);
            }
            return Response::json(['success' => true, 'message' => 'Paiement confirmé']);
        });
    }
}
