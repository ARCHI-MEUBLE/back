<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;
use App\Domain\Shared\IntegrationNotConfiguredException;
use App\Http\Request;
use App\Infrastructure\RateLimit\RateLimiter;

final class BackupAccessGuard
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $logFile,
        private readonly string $rateLimitFile,
        private readonly int $maxPerHour = 10,
    ) {}

    public function check(Request $request): string
    {
        $ip = RateLimiter::clientIp($_SERVER);
        if ($this->apiKey === '') {
            $this->log('AUTH_CHECK', false, $ip, 'BACKUP_API_KEY not configured');
            throw new IntegrationNotConfiguredException('Service temporarily unavailable');
        }
        if (!$this->withinRateLimit($ip)) {
            $this->log('RATE_LIMIT', false, $ip, 'Too many requests');
            throw new DomainException('Too many requests. Try again later.', 429);
        }
        $provided = $request->header('x-api-key') ?? '';
        if ($provided === '' || !hash_equals($this->apiKey, $provided)) {
            $this->log('AUTH', false, $ip, 'Invalid API key');
            throw new ForbiddenException('Forbidden');
        }
        return $ip;
    }

    public function log(string $action, bool $success, string $ip, string $details = ''): void
    {
        $line = sprintf('[%s] %s | IP: %s | Action: %s | %s' . "\n", date('Y-m-d H:i:s'), $success ? 'SUCCESS' : 'FAILED', $ip, $action, $details);
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function withinRateLimit(string $ip): bool
    {
        $handle = @fopen($this->rateLimitFile, 'c+');
        if ($handle === false) {
            return false;
        }
        try {
            flock($handle, LOCK_EX);
            $decoded = json_decode((string) stream_get_contents($handle), true);
            $limits = is_array($decoded) ? $decoded : [];
            $hourAgo = time() - 3600;
            $entries = is_array($limits[$ip] ?? null) ? $limits[$ip] : [];
            $recent = array_values(array_filter($entries, static fn(mixed $timestamp): bool => is_int($timestamp) && $timestamp > $hourAgo));
            if (count($recent) >= $this->maxPerHour) {
                return false;
            }
            $recent[] = time();
            $limits[$ip] = $recent;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($limits));
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
