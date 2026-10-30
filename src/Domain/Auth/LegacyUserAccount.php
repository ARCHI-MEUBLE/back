<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class LegacyUserAccount
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly ?string $name,
    ) {}

    public static function fromRow(array $row): self
    {
        $name = $row['name'] ?? null;
        return new self((string) $row['id'], (string) $row['email'], is_string($name) ? $name : null);
    }
}
