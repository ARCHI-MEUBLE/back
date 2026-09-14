<?php

declare(strict_types=1);

namespace App\Config;

final class Settings
{
    public function __construct(
        public readonly string $appEnv,
        public readonly string $rootDir,
        public readonly string $databaseUrl,
        public readonly string $frontendUrl,
        public readonly CorsSettings $cors,
        public readonly SessionSettings $session,
        public readonly PathSettings $paths,
        public readonly string $backupApiKey,
        public readonly PythonSettings $python,
        public readonly StripeSettings $stripe,
    ) {}

    public static function fromEnv(Env $env, string $rootDir): self
    {
        $appEnv = $env->string('APP_ENV', 'production') ?? 'production';
        $frontendUrl = rtrim($env->string('FRONTEND_URL', 'http://localhost:3000') ?? 'http://localhost:3000', '/');
        return new self(
            $appEnv,
            $rootDir,
            $env->require('DATABASE_URL'),
            $frontendUrl,
            CorsSettings::fromEnv($env, $frontendUrl),
            SessionSettings::fromEnv($env),
            PathSettings::fromEnv($env, $rootDir),
            $env->string('BACKUP_API_KEY', '') ?? '',
            PythonSettings::fromEnv($env, $rootDir),
            StripeSettings::fromEnv($env),
        );
    }

    public function isProduction(): bool
    {
        return $this->appEnv === 'production';
    }
}
