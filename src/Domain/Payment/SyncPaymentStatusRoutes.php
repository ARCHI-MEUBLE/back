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
use Throwable;

final class SyncPaymentStatusRoutes
{
    public function __construct(private readonly Connection $db, private readonly StripeGateway $stripe) {}

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
        $updates = [];
        $logs = [];
        $this->checkFull($order, $updates, $logs);
        $this->checkDeposit($order, $updates, $logs);
        $this->checkBalance($order, $updates, $logs);
        if ($updates !== []) {
            $updates[] = 'updated_at = CURRENT_TIMESTAMP';
            $this->db->execute('UPDATE orders SET ' . implode(', ', array_unique($updates)) . ' WHERE id = ?', [$orderId]);
            $logs[] = 'Base de données mise à jour';
        } else {
            $logs[] = 'Aucune mise à jour nécessaire';
        }
        $final = (array) $this->db->queryOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
        return Response::json(['success' => true, 'message' => 'Synchronisation effectuée avec succès', 'logs' => $logs, 'order' => [
            'id' => $final['id'], 'order_number' => $final['order_number'], 'payment_status' => $final['payment_status'],
            'deposit_payment_status' => $final['deposit_payment_status'], 'balance_payment_status' => $final['balance_payment_status'], 'status' => $final['status'],
        ]]);
    }

    private function checkFull(array $order, array &$updates, array &$logs): void
    {
        $intentId = $order['stripe_payment_intent_id'] ?? null;
        if ($intentId === null) {
            return;
        }
        try {
            $status = $this->stripe->retrievePaymentIntent($intentId)->status;
            if ($status === 'succeeded') {
                $updates[] = "payment_status = 'paid'";
                $updates[] = "status = 'confirmed'";
                if (!$order['confirmed_at']) {
                    $updates[] = 'confirmed_at = CURRENT_TIMESTAMP';
                }
                $logs[] = "Paiement complet confirmé (PI: {$intentId})";
            } elseif (in_array($status, ['canceled', 'failed'], true)) {
                $updates[] = "payment_status = 'failed'";
                $logs[] = "Paiement complet échoué (PI: {$intentId})";
            }
        } catch (Throwable $e) {
            $logs[] = 'Erreur lors de la vérification du paiement complet: ' . $e->getMessage();
        }
    }

    private function checkDeposit(array $order, array &$updates, array &$logs): void
    {
        $intentId = $order['deposit_stripe_intent_id'] ?? null;
        if ($intentId === null) {
            return;
        }
        try {
            $status = $this->stripe->retrievePaymentIntent($intentId)->status;
            if ($status === 'succeeded') {
                $updates[] = "deposit_payment_status = 'paid'";
                $updates[] = "payment_status = 'partially_paid'";
                $updates[] = "status = 'confirmed'";
                if (!$order['confirmed_at']) {
                    $updates[] = 'confirmed_at = CURRENT_TIMESTAMP';
                }
                $logs[] = "Acompte confirmé (PI: {$intentId})";
            } elseif (in_array($status, ['canceled', 'failed'], true)) {
                $updates[] = "deposit_payment_status = 'failed'";
                $logs[] = "Acompte échoué (PI: {$intentId})";
            }
        } catch (Throwable $e) {
            $logs[] = "Erreur lors de la vérification de l'acompte: " . $e->getMessage();
        }
    }

    private function checkBalance(array $order, array &$updates, array &$logs): void
    {
        $intentId = $order['balance_stripe_intent_id'] ?? null;
        if ($intentId === null) {
            return;
        }
        try {
            $status = $this->stripe->retrievePaymentIntent($intentId)->status;
            if ($status === 'succeeded') {
                $updates[] = "balance_payment_status = 'paid'";
                $updates[] = "payment_status = 'paid'";
                $logs[] = "Solde confirmé (PI: {$intentId})";
            } elseif (in_array($status, ['canceled', 'failed'], true)) {
                $updates[] = "balance_payment_status = 'failed'";
                $logs[] = "Solde échoué (PI: {$intentId})";
            }
        } catch (Throwable $e) {
            $logs[] = 'Erreur lors de la vérification du solde: ' . $e->getMessage();
        }
    }
}
