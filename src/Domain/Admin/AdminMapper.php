<?php

declare(strict_types=1);

namespace App\Domain\Admin;

final class AdminMapper
{
    private static function customerName(array $customer): string
    {
        $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        return $name === '' ? 'Client' : $name;
    }

    public static function login(AdminAccount $admin): array
    {
        return ['success' => true, 'admin' => ['email' => $admin->email]];
    }


    public static function sessionRich(AdminAccount $admin, ?int $sessionId): array
    {
        return ['admin' => ['email' => $admin->email, 'username' => $admin->username, 'id' => $sessionId ?? $admin->id]];
    }

    public static function sessionShort(AdminAccount $admin): array
    {
        return ['admin' => ['email' => $admin->email]];
    }

    public static function accounts(array $admins, array $customers): array
    {
        return [
            'success' => true,
            'users' => array_map(static fn(array $c): array => [
                'id' => (string) $c['id'],
                'email' => $c['email'],
                'name' => self::customerName($c),
                'type' => 'user',
                'created_at' => $c['created_at'],
            ], $customers),
            'admins' => array_map(static fn(AdminAccount $a): array => [
                'id' => $a->id,
                'email' => $a->email,
                'username' => $a->username,
                'type' => 'admin',
                'created_at' => $a->createdAt,
            ], $admins),
        ];
    }
}
