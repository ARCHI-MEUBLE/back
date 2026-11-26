<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\Stripe\StripeGateway;
use App\Infrastructure\Stripe\StripeObjectReader;
use Throwable;

final class SyncPaymentStatusRoutes
{
    private const INTENTS = [
        'stripe_payment_intent_id' => ['full', 'payment_status', 'Paiement complet'],
        'deposit_stripe_intent_id' => ['deposit', 'deposit_payment_status', 'Acompte'],
        'balance_stripe_intent_id' => ['balance', 'balance_payment_status', 'Solde'],
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly StripeGateway $stripe,
        private readonly PaymentConfirmationService $confirmation,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/sync-payment-status', [new AdminGuard()], ErrorStyle::Success)
            ->post(null, fn(Request $r): Response => $this->sync($r));
    }

    private function sync(Request $r): Response
    {
        $data = $r->jsonOrEmpty();
        if (!isset($data['order_id'])) {
            throw new DomainException('ID de commande manquant');
        }
        $orderId = (int) $data['order_id'];
        $this->stripe->ensureConfigured();
        $order = $this->db->queryOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($order === null) {
            throw new NotFoundException('Commande introuvable');
        }
        $logs = [];
        foreach (self::INTENTS as $column => [$type, $statusColumn, $label]) {
            $intentId = $order[$column] ?? null;
            if (is_string($intentId) && $intentId !== '') {
                $logs[] = $this->check($orderId, $intentId, $type, $statusColumn, $label);
            }
        }
        $logs = array_values(array_filter($logs, static fn(?string $log): bool => $log !== null));
        if ($logs === []) {
            $logs[] = 'Aucune mise à jour nécessaire';
        }
        $final = (array) $this->db->queryOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
        return Response::json(['success' => true, 'message' => 'Synchronisation effectuée avec succès', 'logs' => $logs, 'order' => [
            'id' => $final['id'], 'order_number' => $final['order_number'], 'payment_status' => $final['payment_status'],
            'deposit_payment_status' => $final['deposit_payment_status'], 'balance_payment_status' => $final['balance_payment_status'], 'status' => $final['status'],
        ]]);
    }

    private function check(int $orderId, string $intentId, string $type, string $statusColumn, string $label): ?string
    {
        try {
            $intent = $this->stripe->retrievePaymentIntent($intentId);
            if ($intent->status === 'succeeded') {
                $metadata = StripeObjectReader::metadataArray($intent);
                if (isset($metadata['order_id']) && (int) $metadata['order_id'] !== $orderId) {
                    return "{$label} ignoré : le paiement {$intentId} concerne une autre commande";
                }
                $metadata['order_id'] = $orderId;
                $metadata['payment_type'] ??= $type;
                $result = $this->confirmation->confirm($intentId, $metadata, StripeObjectReader::amountReceived($intent), (string) $intent->currency);
                return match (true) {
                    $result->order === null => "{$label} : commande introuvable pour le paiement {$intentId}",
                    $result->amountMismatch => "{$label} NON enregistré : le montant payé ne correspond pas au montant dû (PI: {$intentId}). Vérifiez dans Stripe.",
                    $result->alreadyProcessed => "{$label} déjà enregistré (PI: {$intentId})",
                    default => "{$label} confirmé (PI: {$intentId})",
                };
            }
            if ($intent->status === 'canceled') {
                $this->db->execute(
                    "UPDATE orders SET {$statusColumn} = 'failed', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND {$statusColumn} IS DISTINCT FROM 'paid'",
                    [$orderId],
                );
                return "{$label} annulé dans Stripe (PI: {$intentId})";
            }
            return null;
        } catch (Throwable $e) {
            return "Erreur lors de la vérification ({$label}) : " . $e->getMessage();
        }
    }
}
