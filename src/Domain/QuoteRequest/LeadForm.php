<?php

declare(strict_types=1);

namespace App\Domain\QuoteRequest;

use App\Domain\Shared\DomainException;

final class LeadForm
{
    public static function fields(array $form, array $required, array $optional = []): array
    {
        $fields = [];
        foreach ($required + $optional as $name => $maxLength) {
            $value = $form[$name] ?? '';
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value === '' && isset($required[$name])) {
                throw new DomainException('Tous les champs obligatoires doivent être remplis');
            }
            if (mb_strlen($value) > $maxLength) {
                throw new DomainException(sprintf('Le champ %s est trop long (%d caractères maximum)', $name, $maxLength));
            }
            $fields[$name] = $value;
        }
        if (isset($fields['email']) && filter_var($fields['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Adresse email invalide');
        }
        return $fields;
    }
}
