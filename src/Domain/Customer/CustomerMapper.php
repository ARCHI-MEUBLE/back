<?php

declare(strict_types=1);

namespace App\Domain\Customer;

final class CustomerMapper
{
    public static function session(?array $customer): array
    {
        return $customer === null ? ['authenticated' => false] : ['authenticated' => true, 'customer' => $customer];
    }

    public static function login(array $customer): array
    {
        return ['success' => true, 'message' => 'Connexion réussie', 'customer' => $customer];
    }

    public static function registered(RegistrationOutcome $outcome): array
    {
        return [
            'success' => true,
            'message' => $outcome->isNewAccount
                ? 'Compte créé. Veuillez vérifier votre email pour activer votre compte.'
                : 'Un nouveau code de vérification a été envoyé',
            'requiresVerification' => true,
            'email' => $outcome->email,
        ];
    }
}
