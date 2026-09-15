<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

use App\Db\Connection;

final class CalendlyAppointmentRepository
{
    public function __construct(private readonly Connection $db) {}

    public function all(?string $status, int $limit, int $offset): array
    {
        $sql = 'SELECT * FROM calendly_appointments';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY start_time DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        return array_map(CalendlyAppointmentMapper::withFormattedDates(...), $this->db->query($sql, $params));
    }

    public function count(?string $status): int
    {
        return (int) ($status === null
            ? $this->db->scalar('SELECT COUNT(*) FROM calendly_appointments')
            : $this->db->scalar('SELECT COUNT(*) FROM calendly_appointments WHERE status = ?', [$status]));
    }

    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM calendly_appointments WHERE id = ?', [$id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->execute('UPDATE calendly_appointments SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function reschedule(int $id, string $startTime, string $endTime): void
    {
        $this->db->execute("UPDATE calendly_appointments SET start_time = ?, end_time = ?, status = 'scheduled' WHERE id = ?", [$startTime, $endTime, $id]);
    }

    public function upsertFromWebhook(array $data): int
    {
        return $this->db->insertReturningId(
            "INSERT INTO calendly_appointments
                (calendly_event_id, client_name, client_email, event_type, start_time, end_time, timezone, config_url, additional_notes, meeting_url, phone_number, status, confirmation_sent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', TRUE)
             ON CONFLICT (calendly_event_id) DO UPDATE SET
                client_name = EXCLUDED.client_name, client_email = EXCLUDED.client_email, event_type = EXCLUDED.event_type,
                start_time = EXCLUDED.start_time, end_time = EXCLUDED.end_time, timezone = EXCLUDED.timezone,
                config_url = EXCLUDED.config_url, additional_notes = EXCLUDED.additional_notes, meeting_url = EXCLUDED.meeting_url,
                phone_number = EXCLUDED.phone_number, status = EXCLUDED.status, confirmation_sent = EXCLUDED.confirmation_sent
             RETURNING id",
            [
                $data['calendly_event_id'], $data['client_name'], $data['client_email'], $data['event_type'],
                $data['start_time'], $data['end_time'], $data['timezone'], $data['config_url'], $data['additional_notes'],
                $data['meeting_url'] ?? null, $data['phone_number'] ?? null,
            ],
        );
    }

    public function dueForReminder(string $column, string $from, string $to): array
    {
        return $this->db->query(
            "SELECT * FROM calendly_appointments WHERE status = 'scheduled' AND {$column} = FALSE AND start_time BETWEEN ? AND ?",
            [$from, $to],
        );
    }

    public function markReminderSent(string $column, int $id): void
    {
        $this->db->execute("UPDATE calendly_appointments SET {$column} = TRUE, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$id]);
    }
}
