<?php

declare(strict_types=1);

namespace App\Domain\ContactRequest;

use App\Db\Connection;

final class ContactRequestRepository
{
    public function __construct(private readonly Connection $db) {}

    public function create(string $name, string $email, string $phone, string $company, string $subject, string $message): int
    {
        return $this->db->insertReturningId(
            "INSERT INTO contact_requests (name, email, phone, company, subject, message, status) VALUES (?, ?, ?, ?, ?, ?, 'pending') RETURNING id",
            [$name, $email, $phone, $company, $subject, $message],
        );
    }

    public function recent(int $limit): array
    {
        return $this->db->query(
            'SELECT * FROM contact_requests ORDER BY created_at DESC LIMIT ?',
            [$limit],
        );
    }
}
