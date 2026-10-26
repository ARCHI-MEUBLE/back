<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Shared\ConflictException;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Session;
use App\Infrastructure\RateLimit\Throttle;

final class LegacyAuthService
{
    public function __construct(
        private readonly LegacyUserRepository $users,
        private readonly Throttle $throttle,
    ) {}

    public function login(string $email, string $password, string $ip, Session $session): LegacyUserAccount
    {
        $key = strtolower(trim($email));
        $this->throttle->assertAllowed($key, 'legacy_login_account', 5, 30, 'Trop de tentatives de connexion. Réessayez plus tard.');
        $this->throttle->assertAllowed($ip, 'legacy_login_ip', 20, 30, 'Trop de tentatives de connexion. Réessayez plus tard.');
        $user = $this->users->verifyCredentials($email, $password);
        if ($user === null) {
            $this->throttle->fail($key, 'legacy_login_account', 5, 30);
            $this->throttle->fail($ip, 'legacy_login_ip', 20, 30);
            throw new UnauthorizedException('Identifiants invalides');
        }
        $this->throttle->reset($key, 'legacy_login_account');
        $session->regenerate();
        $session->set('user_id', $user->id);
        $session->set('user_email', $user->email);
        $session->set('user_name', $user->name);
        return $user;
    }

    public function register(string $email, string $password, ?string $name, Session $session): LegacyUserAccount
    {
        if (mb_strlen($password) < 8) {
            throw new DomainException('Le mot de passe doit contenir au moins 8 caractères');
        }
        if ($this->users->emailExists($email)) {
            throw new ConflictException('Cet email est déjà utilisé');
        }
        $id = uniqid('user_', true);
        $this->users->create($id, $email, password_hash($password, PASSWORD_BCRYPT), $name);
        $session->regenerate();
        $session->set('user_id', $id);
        $session->set('user_email', $email);
        $session->set('user_name', $name);
        return new LegacyUserAccount($id, $email, $name);
    }

    public function current(Session $session): LegacyUserAccount
    {
        $id = $session->string('user_id');
        if ($id === null) {
            throw new UnauthorizedException();
        }
        return new LegacyUserAccount($id, (string) $session->string('user_email'), $session->string('user_name'));
    }

    public function changePassword(string $currentPassword, string $newPassword, Session $session): void
    {
        $current = $this->current($session);
        if ($this->users->verifyCredentials($current->email, $currentPassword) === null) {
            throw new UnauthorizedException('Mot de passe actuel incorrect');
        }
        if (mb_strlen($newPassword) < 8) {
            throw new DomainException('Le mot de passe doit contenir au moins 8 caractères');
        }
        if ($this->users->updatePasswordHash($current->id, password_hash($newPassword, PASSWORD_BCRYPT)) === 0) {
            throw new DomainException('Erreur lors de la mise à jour du mot de passe', 500);
        }
    }
}
