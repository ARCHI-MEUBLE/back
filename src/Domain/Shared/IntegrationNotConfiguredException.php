<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Throwable;

final class IntegrationNotConfiguredException extends DomainException
{
    public function __construct(string $message = 'Service non configuré', ?Throwable $previous = null)
    {
        parent::__construct($message, 503, $previous);
    }
}
