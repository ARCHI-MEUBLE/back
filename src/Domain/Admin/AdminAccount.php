<?php

declare(strict_types=1);

namespace App\Domain\Admin;

final class AdminAccount
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $createdAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) $row['created_at'],
        );
    }
}
