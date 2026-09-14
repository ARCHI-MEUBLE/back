<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;
use PDO;

final class DatabaseUrl
{
    public static function fromEnv(): string
    {
        $url = getenv('TEST_DATABASE_URL');
        if (!is_string($url) || $url === '') {
            throw new InvalidArgumentException('TEST_DATABASE_URL is not set');
        }
        return $url;
    }

    public static function connect(string $url): PDO
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'], $parts['path'])) {
            throw new InvalidArgumentException('Invalid database url');
        }
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim($parts['path'], '/'),
        );
        $pdo = new PDO($dsn, $parts['user'] ?? null, $parts['pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }
}
