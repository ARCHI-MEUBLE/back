<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Lib\Validation\Field;
use App\Lib\Validation\Schema;
use App\Lib\Validation\ValidationException;

final class CustomerSchemas
{
    public static function register(): Schema
    {
        return Schema::object([
            'email' => Field::string()->required()->email('Email invalide'),
            'password' => Field::string()->required()->minLength(8, 'Le mot de passe doit contenir au moins 8 caractères'),
            'first_name' => Field::string()->required(),
            'last_name' => Field::string()->required(),
            'phone' => Field::string()->required()->pattern('/^(\+33|0)[1-9](\d{2}){4}$/', 'Format de téléphone invalide (ex: 0612345678 ou +33612345678)'),
            'address' => Field::string()->required(),
            'city' => Field::string()->required(),
            'postal_code' => Field::string()->required(),
            'country' => Field::string()->default('France'),
        ]);
    }

    public static function validateRegistration(array $input): array
    {
        foreach (['email', 'password', 'first_name', 'last_name', 'phone', 'address', 'city', 'postal_code', 'country'] as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                throw new ValidationException(sprintf('Le champ %s est requis', $field));
            }
        }
        $data = self::register()->validate(['phone' => str_replace(' ', '', (string) $input['phone'])] + $input);
        if ($data['country'] === 'France' && preg_match('/^\d{5}$/', (string) $input['postal_code']) !== 1) {
            throw new ValidationException('Le code postal doit contenir exactement 5 chiffres');
        }
        return $data;
    }

    public static function login(): Schema
    {
        return Schema::object([
            'email' => Field::string()->required('Email et mot de passe requis'),
            'password' => Field::string()->required('Email et mot de passe requis'),
        ]);
    }

    public static function verifyEmail(): Schema
    {
        return Schema::object([
            'email' => Field::string()->required('Email et code requis'),
            'code' => Field::string()->required('Email et code requis')->pattern('/^\d{6}$/', 'Le code doit contenir 6 chiffres'),
        ]);
    }

    public static function emailOnly(string $message): Schema
    {
        return Schema::object(['email' => Field::string()->required($message)->email('Email invalide')]);
    }

    public static function requiredEmail(string $message): Schema
    {
        return Schema::object(['email' => Field::string()->required($message)]);
    }

    public static function resetPassword(): Schema
    {
        return Schema::object([
            'token' => Field::string()->required('Token et nouveau mot de passe requis'),
            'password' => Field::string()->required('Token et nouveau mot de passe requis')->minLength(8, 'Le mot de passe doit contenir au moins 8 caractères'),
        ]);
    }

    public static function updateBasic(): Schema
    {
        return Schema::object([
            'first_name' => Field::string()->nullable(),
            'last_name' => Field::string()->nullable(),
            'email' => Field::string()->nullable(),
            'phone' => Field::string()->nullable(),
            'address' => Field::string()->nullable(),
        ]);
    }

    public static function changePassword(string $currentKey, string $newKey): Schema
    {
        return Schema::object([
            $currentKey => Field::string()->required('Mot de passe actuel et nouveau mot de passe requis'),
            $newKey => Field::string()->required('Mot de passe actuel et nouveau mot de passe requis')->minLength(6, 'Le nouveau mot de passe doit contenir au moins 6 caractères'),
        ]);
    }

    public static function deleteConfirmation(): Schema
    {
        return Schema::object(['password' => Field::string()->required('Mot de passe requis pour confirmer la suppression')]);
    }
}
