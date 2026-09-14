<?php

declare(strict_types=1);

namespace App\Config;

final class MailSettings
{
    public function __construct(
        public readonly ?string $resendApiKey,
        public readonly string $adminEmail,
        public readonly string $backendUrl,
    ) {}

    public static function fromEnv(Env $env): self
    {
        return new self(
            $env->string('RESEND_API_KEY'),
            $env->string('ADMIN_EMAIL', 'pro.archimeuble@gmail.com') ?? 'pro.archimeuble@gmail.com',
            rtrim($env->string('BACKEND_URL', 'http://127.0.0.1:8000') ?? 'http://127.0.0.1:8000', '/'),
        );
    }
}
