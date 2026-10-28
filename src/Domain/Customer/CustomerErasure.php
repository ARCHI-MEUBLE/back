<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Db\Connection;

final class CustomerErasure
{
    public function __construct(private readonly Connection $db) {}

    public function erase(int $id): bool
    {
        return (bool) $this->db->transaction(function (Connection $db) use ($id): bool {
            if ($db->queryOne('SELECT id FROM customers WHERE id = ? FOR UPDATE', [$id]) === null) {
                return false;
            }
            $hasHistory = $db->scalar('SELECT 1 FROM orders WHERE customer_id = ? LIMIT 1', [$id]) !== null
                || $db->scalar('SELECT 1 FROM payment_installments WHERE customer_id = ? LIMIT 1', [$id]) !== null;
            if (!$hasHistory) {
                return $db->execute('DELETE FROM customers WHERE id = ?', [$id]) > 0;
            }
            foreach (['cart_items', 'cart_sample_items', 'cart_catalogue_items', 'facade_cart_items', 'notifications'] as $table) {
                $db->execute("DELETE FROM {$table} WHERE customer_id = ?", [$id]);
            }
            return $db->execute(
                "UPDATE customers SET email = ?, password_hash = '!', first_name = 'Client', last_name = 'supprimé', phone = NULL, address = NULL,
                        city = NULL, postal_code = NULL, stripe_customer_id = NULL, email_verified = FALSE, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?",
                ['compte-supprime-' . $id . '@archimeuble.invalid', $id],
            ) > 0;
        });
    }
}
