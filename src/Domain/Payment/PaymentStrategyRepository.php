<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;

final class PaymentStrategyRepository
{
    public const DEFAULT_DEPOSIT_PERCENT = 30.0;

    private const STRATEGIES = ['full', 'deposit'];

    public function __construct(private readonly Connection $db) {}

    public function update(int $orderId, string $strategy, ?float $depositPercentage): array
    {
        if (!in_array($strategy, self::STRATEGIES, true)) {
            throw new DomainException('Stratégie de paiement invalide');
        }
        $order = $this->db->queryOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($order === null) {
            throw new NotFoundException('Commande introuvable');
        }
        if (($order['deposit_payment_status'] ?? '') === 'paid' || ($order['payment_status'] ?? '') === 'paid') {
            throw new DomainException('Impossible de modifier la stratégie après un paiement');
        }
        $total = round((float) ($order['total_amount'] ?? 0), 2);
        $percentage = 0.0;
        $depositAmount = 0.0;
        $remainingAmount = $total;
        if ($strategy === 'deposit') {
            $percentage = $depositPercentage ?? $this->defaultDepositPercent();
            if ($percentage <= 0 || $percentage >= 100) {
                throw new DomainException("Le pourcentage d'acompte doit être entre 1 et 99");
            }
            [$depositAmount, $remainingAmount] = self::split($total, $percentage);
        }
        $this->db->transaction(function (Connection $db) use ($orderId, $strategy, $percentage, $depositAmount, $remainingAmount): void {
            $db->execute("UPDATE payment_links SET status = 'revoked' WHERE order_id = ? AND status = 'active'", [$orderId]);
            $db->execute(
                'UPDATE orders SET payment_strategy = ?, deposit_percentage = ?, deposit_amount = ?, remaining_amount = ?,
                        stripe_payment_intent_id = NULL, deposit_stripe_intent_id = NULL, balance_stripe_intent_id = NULL, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?',
                [$strategy, $percentage, $depositAmount, $remainingAmount, $orderId],
            );
        });
        return ['payment_strategy' => $strategy, 'deposit_percentage' => $percentage, 'deposit_amount' => $depositAmount, 'remaining_amount' => $remainingAmount];
    }

    public static function split(float $total, float $percentage): array
    {
        $deposit = round($total * $percentage / 100, 2);
        return [$deposit, round($total - $deposit, 2)];
    }

    public function defaultDepositPercent(): float
    {
        $value = $this->db->scalar(
            "SELECT param_value FROM pricing_config WHERE category = 'payment' AND item_type = 'deposit' AND param_name = 'default_percent' AND is_active = TRUE",
        );
        return is_numeric($value) && (float) $value > 0 && (float) $value < 100 ? (float) $value : self::DEFAULT_DEPOSIT_PERCENT;
    }
}
