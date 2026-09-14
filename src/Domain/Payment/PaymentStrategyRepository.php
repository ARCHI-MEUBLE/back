<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Shared\DomainException;

final class PaymentStrategyRepository
{
    private const STRATEGIES = ['full', 'deposit'];

    public function __construct(private readonly Connection $db) {}

    public function update(int $orderId, string $strategy, float $depositPercentage): void
    {
        if (!in_array($strategy, self::STRATEGIES, true)) {
            throw new DomainException('Stratégie de paiement invalide', 500);
        }
        $order = $this->db->queryOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($order === null) {
            throw new DomainException('Commande introuvable', 500);
        }
        if (($order['deposit_payment_status'] ?? '') === 'paid' || ($order['payment_status'] ?? '') === 'paid') {
            throw new DomainException('Impossible de modifier la stratégie après un paiement', 500);
        }
        $depositAmount = 0.0;
        $remainingAmount = (float) ($order['total_amount'] ?? $order['total'] ?? 0);
        if ($strategy === 'deposit') {
            if ($depositPercentage <= 0 || $depositPercentage >= 100) {
                throw new DomainException("Le pourcentage d'acompte doit être entre 1 et 99", 500);
            }
            [$depositAmount, $remainingAmount] = $this->depositSplit($orderId, $depositPercentage, $remainingAmount);
        }
        $this->db->execute(
            'UPDATE orders SET payment_strategy = ?, deposit_percentage = ?, deposit_amount = ?, remaining_amount = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$strategy, $depositPercentage, $depositAmount, $remainingAmount, $orderId],
        );
    }

    private function depositSplit(int $orderId, float $depositPercentage, float $total): array
    {
        $furnitureTotal = (float) ($this->db->scalar('SELECT COALESCE(SUM(total_price), 0) FROM order_items WHERE order_id = ?', [$orderId]) ?? 0);
        $samplesTotal = (float) ($this->db->scalar('SELECT COALESCE(SUM(price * quantity), 0) FROM order_sample_items WHERE order_id = ?', [$orderId]) ?? 0);
        $catalogueTotal = (float) ($this->db->scalar('SELECT COALESCE(SUM(COALESCE(total_price, unit_price * quantity)), 0) FROM order_catalogue_items WHERE order_id = ?', [$orderId]) ?? 0);
        $facadeTotal = (float) ($this->db->scalar('SELECT COALESCE(SUM(COALESCE(total_price, unit_price * quantity)), 0) FROM order_facade_items WHERE order_id = ?', [$orderId]) ?? 0);
        $depositAmount = ($furnitureTotal + $facadeTotal) * ($depositPercentage / 100) + $samplesTotal + $catalogueTotal;
        return [$depositAmount, $total - $depositAmount];
    }
}
