<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Db\Connection;

final class CustomerVerificationRepository
{
    public function __construct(private readonly Connection $db) {}

    public function issueCode(string $email, string $code, string $expiresAt): void
    {
        $this->db->execute('DELETE FROM email_verifications WHERE email = ? AND used = FALSE', [$email]);
        $this->db->execute('INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)', [$email, $code, $expiresAt]);
    }

    public function recentCodeCount(string $email): int
    {
        return (int) ($this->db->scalar(
            "SELECT COUNT(*) FROM email_verifications WHERE email = ? AND created_at > NOW() - INTERVAL '1 hour'",
            [$email],
        ) ?? 0);
    }

    public function findValidUnusedCode(string $email, string $code): ?array
    {
        return $this->db->queryOne(
            'SELECT * FROM email_verifications WHERE email = ? AND code = ? AND used = FALSE ORDER BY created_at DESC LIMIT 1',
            [$email, $code],
        );
    }

    public function markCodeUsed(int $id): void
    {
        $this->db->execute('UPDATE email_verifications SET used = TRUE WHERE id = ?', [$id]);
    }

    public function clearCodesForEmail(string $email): void
    {
        $this->db->execute('DELETE FROM email_verifications WHERE email = ?', [$email]);
    }

    public function issueResetToken(string $email, string $token, string $expiresAt): void
    {
        $this->db->execute('DELETE FROM password_resets WHERE email = ?', [$email]);
        $this->db->execute('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)', [$email, $token, $expiresAt]);
    }

    public function findValidResetToken(string $token): ?array
    {
        return $this->db->queryOne('SELECT * FROM password_resets WHERE token = ? AND expires_at > CURRENT_TIMESTAMP', [$token]);
    }

    public function clearResetTokensForEmail(string $email): void
    {
        $this->db->execute('DELETE FROM password_resets WHERE email = ?', [$email]);
    }
}
