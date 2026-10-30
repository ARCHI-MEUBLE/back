<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class LegacyAuthMapper
{
    public static function session(LegacyUserAccount $user): array
    {
        return self::userField($user);
    }

    public static function loggedIn(LegacyUserAccount $user): array
    {
        return ['success' => true] + self::userField($user);
    }

    public static function registered(LegacyUserAccount $user): array
    {
        return ['success' => true] + self::userField($user);
    }

    private static function userField(LegacyUserAccount $user): array
    {
        return ['user' => ['id' => $user->id, 'email' => $user->email, 'name' => $user->name]];
    }
}
