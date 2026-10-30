<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use RuntimeException;
use Throwable;

class DomainException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
