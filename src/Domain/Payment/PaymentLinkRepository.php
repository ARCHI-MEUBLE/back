<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Db\Connection;
use App\Domain\Shared\DomainException;

final class PaymentLinkRepository
{
    public function __construct(private readonly Connection $db) {}

    public function generateLink(int $orderId, string $adminEmail, int $expiryDays, string $paymentType, ?float $amount): array
    {
        $order = $this->db->queryOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($order === null) {
            throw new DomainException('Commande introuvable');
        }
        if ($paymentType === 'full' && $order['payment_status'] === 'paid') {
            throw new DomainException('Cette commande a déjà été payée intégralement');
        }
        if ($paymentType === 'deposit' && ($order['deposit_payment_status'] ?? 'pending') === 'paid') {
            throw new DomainException("L'acompte pour cette commande a déjà été payé");
        }
        if ($paymentType === 'balance' && ($order['balance_payment_status'] ?? 'pending') === 'paid') {
            throw new DomainException('Le solde pour cette commande a déjà été payé');
        }
        if ($amount === null) {
            $amount = match ($paymentType) {
                'deposit' => (float) ($order['deposit_amount'] ?? 0),
                'balance' => (float) ($order['remaining_amount'] ?? 0),
                default => (float) ($order['total_amount'] ?? $order['total'] ?? 0),
            };
        }
        if ($amount <= 0) {
            throw new DomainException('Le montant à payer doit être supérieur à 0€');
        }
        $token = self::generateSecureToken();
        $expiryTimestamp = strtotime("+{$expiryDays} days");
        $expiresAt = date('Y-m-d H:i:s', $expiryTimestamp === false ? time() : $expiryTimestamp);
        $linkId = $this->db->insertReturningId(
            "INSERT INTO payment_links (order_id, token, expires_at, created_by_admin, status, payment_type, amount) VALUES (?, ?, ?, ?, 'active', ?, ?) RETURNING id",
            [$orderId, $token, $expiresAt, $adminEmail, $paymentType, $amount],
        );
        return ['id' => $linkId, 'token' => $token, 'order_id' => $orderId, 'expires_at' => $expiresAt, 'status' => 'active', 'created_by_admin' => $adminEmail, 'payment_type' => $paymentType, 'amount' => $amount];
    }

    public function getLinkByToken(string $token): ?array
    {
        return $this->db->queryOne(
            'SELECT pl.*, pl.id as link_id, o.id as order_id, o.order_number, o.total_amount, o.status as order_status,
                    o.shipping_address, o.billing_address, o.payment_status, o.deposit_percentage, o.deposit_amount, o.remaining_amount,
                    o.created_at as order_created_at, c.email, c.first_name, c.last_name, c.phone
             FROM payment_links pl
             JOIN orders o ON pl.order_id = o.id
             LEFT JOIN customers c ON o.customer_id = c.id
             WHERE pl.token = ?',
            [$token],
        );
    }

    public function getOrderItemsByToken(string $token): ?array
    {
        $link = $this->getLinkByToken($token);
        if ($link === null) {
            return null;
        }
        $configs = $this->db->query(
            'SELECT oi.*, sc.name as config_name, sc.thumbnail_url FROM order_items oi
             LEFT JOIN saved_configurations sc ON oi.configuration_id = sc.id WHERE oi.order_id = ?',
            [$link['order_id']],
        );
        $samples = $this->db->query('SELECT * FROM order_sample_items WHERE order_id = ?', [$link['order_id']]);
        return ['configurations' => $configs, 'samples' => $samples, 'order' => $link];
    }

    public function validateLink(string $token): array
    {
        $link = $this->getLinkByToken($token);
        if ($link === null) {
            return ['valid' => false, 'message' => 'Lien de paiement invalide ou introuvable', 'link' => null];
        }
        if (strtotime($link['expires_at']) < time()) {
            return ['valid' => false, 'message' => 'Ce lien de paiement a expiré', 'link' => $link];
        }
        if ($link['status'] === 'used') {
            return ['valid' => false, 'message' => 'Ce lien de paiement a déjà été utilisé', 'link' => $link];
        }
        if ($link['status'] === 'revoked') {
            return ['valid' => false, 'message' => 'Ce lien de paiement a été révoqué', 'link' => $link];
        }
        if ($link['payment_status'] === 'paid') {
            return ['valid' => false, 'message' => 'Cette commande a déjà été payée', 'link' => $link];
        }
        return ['valid' => true, 'message' => 'Lien valide', 'link' => $link];
    }

    public function markAsAccessed(string $token): void
    {
        $this->db->execute('UPDATE payment_links SET accessed_at = CURRENT_TIMESTAMP WHERE token = ? AND accessed_at IS NULL', [$token]);
    }

    public function markAsUsed(string $token): void
    {
        $this->db->execute("UPDATE payment_links SET status = 'used', paid_at = CURRENT_TIMESTAMP WHERE token = ?", [$token]);
    }

    public function revokeLink(int $linkId): void
    {
        $this->db->execute("UPDATE payment_links SET status = 'revoked' WHERE id = ?", [$linkId]);
    }

    public function findById(int $linkId): ?array
    {
        return $this->db->queryOne('SELECT * FROM payment_links WHERE id = ?', [$linkId]);
    }

    public function getLinksByOrderId(int $orderId): array
    {
        return $this->db->query(
            'SELECT pl.*, o.order_number FROM payment_links pl LEFT JOIN orders o ON pl.order_id = o.id WHERE pl.order_id = ? ORDER BY pl.created_at DESC',
            [$orderId],
        );
    }

    private static function generateSecureToken(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
