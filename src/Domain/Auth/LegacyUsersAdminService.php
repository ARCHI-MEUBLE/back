<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Admin\AdminRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\ForbiddenException;

final class LegacyUsersAdminService
{
    public function __construct(
        private readonly LegacyUserRepository $users,
        private readonly AdminRepository $admins,
    ) {}

    public function list(): array
    {
        return [
            'users' => array_map(static fn(array $u): array => [
                'id' => $u['id'],
                'email' => $u['email'],
                'name' => $u['name'] ?? null,
                'type' => 'user',
                'created_at' => $u['created_at'],
            ], $this->users->all()),
            'admins' => array_map(static fn($a): array => [
                'id' => $a->id,
                'email' => $a->email,
                'username' => $a->username,
                'type' => 'admin',
                'created_at' => $a->createdAt,
            ], $this->admins->all()),
        ];
    }

    public function resetPassword(int|string $id, string $type, string $newPassword): void
    {
        if (!in_array($type, ['user', 'admin'], true)) {
            throw new DomainException('Type invalide');
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $affected = $type === 'user'
            ? $this->users->applyWhitelistedUpdate((string) $id, ['password_hash' => $hash])
            : $this->admins->applyWhitelistedUpdate((int) $id, ['password' => $hash]);
        if ($affected === 0) {
            throw new DomainException('Échec de la modification', 500);
        }
    }

    public function delete(int|string $id, string $type): void
    {
        if ($type !== 'user') {
            throw new ForbiddenException('Impossible de supprimer un admin');
        }
        $this->users->delete((string) $id);
    }
}
