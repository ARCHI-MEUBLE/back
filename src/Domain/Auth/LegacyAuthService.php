<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Shared\ConflictException;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\UnauthorizedException;
use App\Http\Session;

final class LegacyAuthService
{
    public function __construct(private readonly LegacyUserRepository $users) {}

    public function login(string $email, string $password, Session $session): LegacyUserAccount
    {
        $user = $this->users->verifyCredentials($email, $password);
        if ($user === null) {
            throw new UnauthorizedException('Identifiants invalides');
        }
        $session->set('user_id', $user->id);
        $session->set('user_email', $user->email);
        $session->set('user_name', $user->name);
        return $user;
    }

    public function register(string $email, string $password, ?string $name, Session $session): LegacyUserAccount
    {
        if ($this->users->emailExists($email)) {
            throw new ConflictException('Cet email est déjà utilisé');
        }
        $id = uniqid('user_', true);
        $this->users->create($id, $email, password_hash($password, PASSWORD_BCRYPT), $name);
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
        $affected = $this->users->applyWhitelistedUpdate($current->id, ['password' => $newPassword]);
        if ($affected === 0) {
            throw new DomainException('Erreur lors de la mise à jour du mot de passe', 500);
        }
    }
}
