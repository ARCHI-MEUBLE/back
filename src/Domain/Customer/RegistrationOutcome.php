<?php

declare(strict_types=1);

namespace App\Domain\Customer;

final class RegistrationOutcome
{
    public function __construct(
        public readonly string $email,
        public readonly bool $isNewAccount,
    ) {}
}
