<?php

declare(strict_types=1);

namespace App\Domain\Admin;

use App\Db\Connection;
use App\Domain\Shared\ConflictException;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;

final class AdminAccountsService
{
    public function __construct(
        private readonly Connection $db,
        private readonly AdminRepository $admins,
    ) {}

    public function list(): array
    {
        $customers = $this->db->query('SELECT id, email, first_name, last_name, created_at FROM customers ORDER BY created_at DESC');
        return AdminMapper::accounts($this->admins->all(), $customers);
    }

    public function resetPassword(int|string $id, string $type, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $affected = match ($type) {
            'user' => $this->db->execute('UPDATE customers SET password_hash = ? WHERE id = ?', [$hash, (int) $id]),
            'admin' => $this->db->execute('UPDATE admins SET password = ?, password_hash = ? WHERE id = ?', [$hash, $hash, (int) $id]),
            default => throw new DomainException('Type de compte invalide'),
        };
        if ($affected === 0) {
            throw new NotFoundException('Compte non trouvé');
        }
    }

    public function delete(int|string $id, string $type): void
    {
        $affected = match ($type) {
            'user' => $this->db->execute('DELETE FROM customers WHERE id = ?', [(int) $id]),
            'admin' => $this->deleteAdmin((int) $id),
            default => throw new DomainException('Type de compte invalide'),
        };
        if ($affected === 0) {
            throw new NotFoundException('Compte non trouvé');
        }
    }

    private function deleteAdmin(int $id): int
    {
        if ($this->admins->count() <= 1) {
            throw new ConflictException('Impossible de supprimer le dernier administrateur');
        }
        return $this->admins->delete($id) ? 1 : 0;
    }
}
