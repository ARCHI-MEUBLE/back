<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

final class ConfigurationSaveResult
{
    public function __construct(
        public readonly array $payload,
        public readonly int $status,
    ) {}
}
