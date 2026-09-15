<?php

declare(strict_types=1);

namespace App\Domain\Admin;

use App\Domain\Shared\ConflictException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Session;
use App\Infrastructure\RateLimit\RateLimiter;

final class AdminAuthService
{
    public function __construct(
        private readonly AdminRepository $admins,
        private readonly RateLimiter $rateLimiter,
    ) {}

    public function loginRateLimited(string $email, string $password, string $ip, Session $session): AdminAccount
    {
        $ipDecision = $this->rateLimiter->check($ip, 'admin_login_ip', 5, 30);
        $accountDecision = $this->rateLimiter->check($email, 'admin_login_account', 3, 60);
        if (!$ipDecision->allowed || !$accountDecision->allowed) {
            $blocking = $ipDecision->allowed ? $accountDecision : $ipDecision;
            throw new ConflictException($blocking->message ?? 'Trop de tentatives de connexion');
        }
        $admin = $this->admins->verifyCredentials($email, $password);
        if ($admin === null) {
            $this->rateLimiter->hit($ip, 'admin_login_ip', false, 5, 60);
            $this->rateLimiter->hit($email, 'admin_login_account', false, 3, 120);
            throw new UnauthorizedException('Identifiants invalides');
        }
        $this->rateLimiter->resetAttempts($ip, 'admin_login_ip');
        $this->rateLimiter->resetAttempts($email, 'admin_login_account');
        $session->regenerate();
        $session->remove('customer_id');
        $session->remove('customer_email');
        $session->remove('customer_name');
        $session->set('admin_email', $admin->email);
        $session->set('admin_id', $admin->id);
        $session->set('is_admin', true);
        return $admin;
    }

    public function loginLegacy(string $email, string $password, Session $session): AdminAccount
    {
        $admin = $this->admins->verifyCredentials($email, $password);
        if ($admin === null) {
            throw new UnauthorizedException('Identifiants invalides');
        }
        $session->set('admin_email', $admin->email);
        $session->set('is_admin', true);
        return $admin;
    }

    public function currentRich(Session $session): ?AdminAccount
    {
        if ($session->has('customer_id')) {
            return null;
        }
        $email = $session->string('admin_email');
        if ($email === null || $email === '') {
            return null;
        }
        return $this->admins->findByEmail($email) ?? new AdminAccount($session->int('admin_id') ?? 0, 'Admin', $email, '');
    }

    public function currentStrict(Session $session): AdminAccount
    {
        if (!$session->isAdmin()) {
            throw new UnauthorizedException();
        }
        return new AdminAccount($session->int('admin_id') ?? 0, '', (string) $session->string('admin_email'), '');
    }
}
