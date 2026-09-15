<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Throwable;

final class NotFoundException extends DomainException
{
    public function __construct(string $message = 'Ressource introuvable', ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
