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
    private const MAX_PER_HOUR = 10;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $logFile,
        private readonly string $rateLimitFile,
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
        $provided = $request->queryString('key') ?? '';
        if ($provided === '' || $provided !== $this->apiKey) {
            $this->log('AUTH', false, $ip, 'Invalid API key');
            throw new ForbiddenException('Forbidden');
        }
        return $ip;
    }

    public function log(string $action, bool $success, string $ip, string $details = ''): void
    {
        $line = sprintf('[%s] %s | IP: %s | Action: %s | %s' . "\n", date('Y-m-d H:i:s'), $success ? 'SUCCESS' : 'FAILED', $ip, $action, $details);
        file_put_contents($this->logFile, $line, FILE_APPEND);
    }

    private function withinRateLimit(string $ip): bool
    {
        $raw = is_file($this->rateLimitFile) ? (string) file_get_contents($this->rateLimitFile) : '';
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        $limits = is_array($decoded) ? $decoded : [];
        $now = time();
        $hourAgo = $now - 3600;
        $entries = is_array($limits[$ip] ?? null) ? $limits[$ip] : [];
        $recent = array_values(array_filter($entries, static fn(mixed $timestamp): bool => is_int($timestamp) && $timestamp > $hourAgo));
        if (count($recent) >= self::MAX_PER_HOUR) {
            return false;
        }
        $recent[] = $now;
        $limits[$ip] = $recent;
        file_put_contents($this->rateLimitFile, json_encode($limits));
        return true;
    }
}
