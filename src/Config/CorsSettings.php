<?php

declare(strict_types=1);

namespace App\Config;

final class CorsSettings
{
    private const LOCAL_ORIGINS = ['http://localhost:3000', 'http://localhost:3001', 'http://127.0.0.1:3000', 'http://127.0.0.1:3001'];
    private const PRODUCTION_ORIGINS = ['https://archimeuble.com', 'https://www.archimeuble.com', 'https://dev.archimeuble.com'];

    public function __construct(
        public readonly array $origins,
        public readonly array $suffixes,
    ) {}

    public static function fromEnv(Env $env, string $frontendUrl): self
    {
        $origins = array_merge(
            self::LOCAL_ORIGINS,
            self::PRODUCTION_ORIGINS,
            [$frontendUrl],
            $env->list('CORS_ALLOWED_ORIGINS'),
        );
        return new self(
            array_values(array_unique($origins)),
            $env->list('CORS_ALLOWED_ORIGIN_SUFFIXES', ['.vercel.app', '.archimeuble.com']),
        );
    }

    public function allows(string $origin): bool
    {
        if ($origin === '') {
            return false;
        }
        if (in_array($origin, $this->origins, true)) {
            return true;
        }
        $host = parse_url($origin, PHP_URL_HOST);
        $scheme = parse_url($origin, PHP_URL_SCHEME);
        if (!is_string($host) || $scheme !== 'https') {
            return false;
        }
        foreach ($this->suffixes as $suffix) {
            if (is_string($suffix) && str_ends_with($host, $suffix)) {
                return true;
            }
        }
        return false;
    }
}
