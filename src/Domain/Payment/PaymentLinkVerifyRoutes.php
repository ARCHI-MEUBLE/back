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
use App\Infrastructure\Stripe\StripeObjectReader;

final class PaymentLinkVerifyRoutes
{
    public function __construct(
        private readonly Connection $db,
        private readonly StripeGateway $stripe,
        private readonly PaymentConfirmationService $confirmation,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('payment-link/verify-payment', errorStyle: ErrorStyle::Success)->post(null, fn(Request $r): Response => $this->verify($r));
    }

    private function verify(Request $r): Response
    {
        $data = $r->jsonOrEmpty();
        if (!isset($data['payment_intent_id'])) {
            throw new DomainException('Payment Intent ID manquant');
        }
        $intentId = trim((string) $data['payment_intent_id']);
        if (preg_match('/^pi_[A-Za-z0-9_]+$/', $intentId) !== 1) {
            throw new DomainException('Payment Intent ID invalide');
        }
        $this->stripe->ensureConfigured();
        $intent = $this->stripe->retrievePaymentIntent($intentId);
        if ($intent->status !== 'succeeded') {
            return Response::json(['success' => false, 'status' => $intent->status, 'message' => 'Paiement non confirmé']);
        }
        $metadata = StripeObjectReader::metadataArray($intent);
        $result = $this->confirmation->confirm($intentId, $metadata, StripeObjectReader::amountReceived($intent), (string) $intent->currency);
        if ($result->order === null) {
            throw new NotFoundException('Commande introuvable pour ce paiement');
        }
        if ($result->amountMismatch) {
            return Response::json(['success' => false, 'status' => 'amount_mismatch', 'message' => 'Le montant payé ne correspond pas à la commande. Notre équipe a été prévenue.'], 409);
        }
        $final = $this->db->queryOne(
            'SELECT id, order_number, payment_status, deposit_payment_status, balance_payment_status, status FROM orders WHERE id = ?',
            [$result->order['id']],
        );
        if ($final === null) {
            throw new DomainException("Impossible de récupérer l'état final de la commande", 500);
        }
        return Response::json(['success' => true, 'status' => 'succeeded', 'message' => 'Paiement confirmé et enregistré avec succès', 'already_processed' => $result->alreadyProcessed, 'order' => [
            'id' => $final['id'], 'order_number' => $final['order_number'], 'payment_status' => $final['payment_status'] ?? 'pending',
            'deposit_payment_status' => $final['deposit_payment_status'] ?? 'pending', 'balance_payment_status' => $final['balance_payment_status'] ?? 'pending', 'status' => $final['status'],
        ]]);
    }
}
