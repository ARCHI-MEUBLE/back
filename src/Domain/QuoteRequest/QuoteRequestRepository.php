<?php

declare(strict_types=1);

namespace App\Domain\QuoteRequest;

use App\Db\Connection;

final class QuoteRequestRepository
{
    public function __construct(private readonly Connection $db) {}

    public function create(string $firstName, string $lastName, string $email, string $phone, string $description): int
    {
        return $this->db->insertReturningId(
            "INSERT INTO quote_requests (first_name, last_name, email, phone, description, status) VALUES (?, ?, ?, ?, ?, 'pending') RETURNING id",
            [$firstName, $lastName, $email, $phone, $description],
        );
    }

    public function addFile(int $quoteRequestId, string $fileName, string $storedName, string $fileType, int $fileSize): void
    {
        $this->db->execute(
            'INSERT INTO quote_request_files (quote_request_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)',
            [$quoteRequestId, $fileName, $storedName, $fileType, $fileSize],
        );
    }

    public function recent(int $limit): array
    {
        return $this->db->query(
            'SELECT qr.*, COUNT(qrf.id) as file_count FROM quote_requests qr
             LEFT JOIN quote_request_files qrf ON qr.id = qrf.quote_request_id
             GROUP BY qr.id ORDER BY qr.created_at DESC LIMIT ?',
            [$limit],
        );
    }
}
