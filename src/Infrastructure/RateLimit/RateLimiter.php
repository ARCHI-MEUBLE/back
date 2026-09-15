<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

use App\Db\Connection;

final class RateLimiter
{
    private const LOCKOUT_MULTIPLIER = 2;
    private const MAX_LOCKOUT_MINUTES = 1440;

    public function __construct(private readonly Connection $db) {}

    public function check(string $identifier, string $type, int $maxAttempts, int $decayMinutes): RateLimitDecision
    {
        $this->cleanup();
        $record = $this->record($identifier, $type);
        if ($record === null) {
            return RateLimitDecision::allow($maxAttempts);
        }
        $lockedUntil = is_string($record['locked_until'] ?? null) ? strtotime($record['locked_until']) : false;
        if (is_int($lockedUntil) && $lockedUntil > time()) {
            return $this->lockedDecision($lockedUntil);
        }
        $firstAttempt = is_string($record['first_attempt_at'] ?? null) ? strtotime($record['first_attempt_at']) : time();
        $windowEnd = (is_int($firstAttempt) ? $firstAttempt : time()) + $decayMinutes * 60;
        if (time() > $windowEnd) {
            $this->resetAttempts($identifier, $type);
            return RateLimitDecision::allow($maxAttempts);
        }
        $remaining = max(0, $maxAttempts - (int) $record['attempts']);
        return $remaining > 0
            ? RateLimitDecision::allow($remaining)
            : RateLimitDecision::deny($windowEnd - time(), 'Limite de tentatives atteinte.');
    }

    public function hit(string $identifier, string $type, bool $success, int $maxAttempts, int $lockoutMinutes): void
    {
        if ($success) {
            $this->resetAttempts($identifier, $type);
            return;
        }
        $record = $this->record($identifier, $type);
        $record === null
            ? $this->insertFirstAttempt($identifier, $type)
            : $this->recordFurtherAttempt($identifier, $type, $record, $maxAttempts, $lockoutMinutes);
    }

    public function resetAttempts(string $identifier, string $type): void
    {
        $this->db->execute(
            'UPDATE rate_limits SET attempts = 0, first_attempt_at = NULL, locked_until = NULL, updated_at = NOW() WHERE identifier = ? AND type = ?',
            [$identifier, $type],
        );
    }

    public static function clientIp(array $server): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $header) {
            $value = $server[$header] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }
            $ip = trim(explode(',', $value)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }
        return '0.0.0.0';
    }

    private function lockedDecision(int $lockedUntil): RateLimitDecision
    {
        $retryAfter = $lockedUntil - time();
        return RateLimitDecision::deny($retryAfter, sprintf('Trop de tentatives. Réessayez dans %d minute(s).', (int) ceil($retryAfter / 60)));
    }

    private function insertFirstAttempt(string $identifier, string $type): void
    {
        $this->db->execute(
            'INSERT INTO rate_limits (identifier, type, attempts, first_attempt_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())',
            [$identifier, $type],
        );
    }

    private function recordFurtherAttempt(string $identifier, string $type, array $record, int $maxAttempts, int $lockoutMinutes): void
    {
        $attempts = (int) $record['attempts'] + 1;
        $lockoutCount = (int) ($record['lockout_count'] ?? 0);
        if ($attempts < $maxAttempts) {
            $this->db->execute('UPDATE rate_limits SET attempts = ?, updated_at = NOW() WHERE identifier = ? AND type = ?', [$attempts, $identifier, $type]);
            return;
        }
        $lockout = min($lockoutMinutes * (self::LOCKOUT_MULTIPLIER ** $lockoutCount), self::MAX_LOCKOUT_MINUTES);
        $this->db->execute(
            'UPDATE rate_limits SET attempts = ?, lockout_count = ?, locked_until = NOW() + make_interval(mins => ?), updated_at = NOW() WHERE identifier = ? AND type = ?',
            [$attempts, $lockoutCount + 1, $lockout, $identifier, $type],
        );
    }

    private function record(string $identifier, string $type): ?array
    {
        return $this->db->queryOne('SELECT * FROM rate_limits WHERE identifier = ? AND type = ?', [$identifier, $type]);
    }

    private function cleanup(): void
    {
        $this->db->execute("DELETE FROM rate_limits WHERE updated_at < NOW() - INTERVAL '24 hours' AND (locked_until IS NULL OR locked_until < NOW())");
    }
}
