<?php

declare(strict_types=1);

namespace App\Lib\Validation;

use App\Domain\Shared\DomainException;
use Throwable;

final class ValidationException extends DomainException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 400, $previous);
    }
}
