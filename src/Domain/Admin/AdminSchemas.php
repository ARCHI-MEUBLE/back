<?php

declare(strict_types=1);

namespace App\Domain\Admin;

use App\Lib\Validation\Field;
use App\Lib\Validation\Schema;

final class AdminSchemas
{
    public static function login(): Schema
    {
        return Schema::object([
            'email' => Field::string()->required('Email et mot de passe requis'),
            'password' => Field::string()->required('Email et mot de passe requis'),
        ]);
    }

    public static function resetPassword(): Schema
    {
        return Schema::object([
            'id' => Field::any()->required('Paramètres manquants (id, type, newPassword requis)'),
            'type' => Field::string()->required('Paramètres manquants (id, type, newPassword requis)'),
            'newPassword' => Field::string()->minLength(6, 'Le mot de passe doit contenir au moins 6 caractères')->required('Paramètres manquants (id, type, newPassword requis)'),
        ]);
    }

    public static function deleteAccount(): Schema
    {
        return Schema::object([
            'id' => Field::any()->required('Paramètres manquants (id, type requis)'),
            'type' => Field::string()->required('Paramètres manquants (id, type requis)'),
        ]);
    }
}
