<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Throwable;

final class UnauthorizedException extends DomainException
{
    public function __construct(string $message = 'Non authentifié', ?Throwable $previous = null)
    {
        parent::__construct($message, 401, $previous);
    }
}
