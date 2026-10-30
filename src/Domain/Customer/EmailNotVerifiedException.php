<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Domain\Shared\DomainException;

final class EmailNotVerifiedException extends DomainException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct("Votre adresse email n'est pas encore vérifiée. Un code de vérification vient de vous être envoyé.", 403);
    }
}
