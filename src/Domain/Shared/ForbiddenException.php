<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Throwable;

final class ForbiddenException extends DomainException
{
    public function __construct(string $message = 'Accès refusé', ?Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}
