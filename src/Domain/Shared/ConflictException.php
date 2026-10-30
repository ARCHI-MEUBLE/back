<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Throwable;

final class ConflictException extends DomainException
{
    public function __construct(string $message = 'Conflit', ?Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}
