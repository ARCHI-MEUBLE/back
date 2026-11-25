<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Db\Connection;
use App\Domain\Customer\CustomerErasure;
use DateTimeImmutable;

final class PersonalDataRetention
{
    public const PROSPECT_YEARS = 3;
    public const IP_DAYS = 30;
    public const EXPIRED_TOKEN_GRACE_DAYS = 1;

    public function __construct(
        private readonly Connection $db,
        private readonly string $quoteFilesDir,
    ) {}

    public function purge(DateTimeImmutable $now, bool $dryRun = false): array
    {
        $prospectCutoff = self::format($now->modify('-' . self::PROSPECT_YEARS . ' years'));
        $ipCutoff = self::format($now->modify('-' . self::IP_DAYS . ' days'));
        $tokenCutoff = self::format($now->modify('-' . self::EXPIRED_TOKEN_GRACE_DAYS . ' days'));

        return [
            'quote_requests' => $this->purgeQuoteRequests($prospectCutoff, $dryRun),
            'contact_requests' => $this->deleteWhere('contact_requests', 'COALESCE(updated_at, created_at) < ?', [$prospectCutoff], $dryRun),
            'calendly_appointments' => $this->deleteWhere('calendly_appointments', 'COALESCE(start_time, created_at) < ?', [$prospectCutoff], $dryRun),
            'inactive_customers' => $this->purgeInactiveCustomers($prospectCutoff, $dryRun),
            'rate_limits' => $this->deleteWhere('rate_limits', 'COALESCE(updated_at, created_at) < ? AND (locked_until IS NULL OR locked_until < ?)', [$ipCutoff, self::format($now)], $dryRun),
            'email_verifications' => $this->deleteWhere('email_verifications', 'expires_at < ?', [$tokenCutoff], $dryRun),
            'password_resets' => $this->deleteWhere('password_resets', 'expires_at < ?', [$tokenCutoff], $dryRun),
        ];
    }

    private function purgeQuoteRequests(string $cutoff, bool $dryRun): int
    {
        $ids = array_map('intval', array_column(
            $this->db->query('SELECT id FROM quote_requests WHERE COALESCE(updated_at, created_at) < ?', [$cutoff]),
            'id',
        ));
        if ($dryRun || $ids === []) {
            return count($ids);
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $files = array_column(
            $this->db->query("SELECT file_path FROM quote_request_files WHERE quote_request_id IN ({$placeholders})", $ids),
            'file_path',
        );
        $deleted = $this->db->execute("DELETE FROM quote_requests WHERE id IN ({$placeholders})", $ids);
        foreach ($files as $file) {
            $this->removeQuoteFile((string) $file);
        }
        return $deleted;
    }

    private function purgeInactiveCustomers(string $cutoff, bool $dryRun): int
    {
        $ids = array_map('intval', array_column($this->db->query(
            'SELECT c.id FROM customers c
             WHERE GREATEST(c.created_at, COALESCE(c.updated_at, c.created_at), COALESCE(c.last_activity_at, c.created_at)) < ?
               AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.id)
               AND NOT EXISTS (SELECT 1 FROM payment_installments p WHERE p.customer_id = c.id)',
            [$cutoff],
        ), 'id'));
        if ($dryRun) {
            return count($ids);
        }
        $erasure = new CustomerErasure($this->db);
        $deleted = 0;
        foreach ($ids as $id) {
            $deleted += $erasure->erase($id) ? 1 : 0;
        }
        return $deleted;
    }

    private function deleteWhere(string $table, string $condition, array $params, bool $dryRun): int
    {
        if ($dryRun) {
            return (int) $this->db->scalar("SELECT count(*) FROM {$table} WHERE {$condition}", $params);
        }
        return $this->db->execute("DELETE FROM {$table} WHERE {$condition}", $params);
    }

    private function removeQuoteFile(string $storedName): void
    {
        $name = basename($storedName);
        if ($name === '' || $name === '.' || $name === '..') {
            return;
        }
        $path = rtrim($this->quoteFilesDir, '/') . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function format(DateTimeImmutable $moment): string
    {
        return $moment->format('Y-m-d H:i:s');
    }
}
