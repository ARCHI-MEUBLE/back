<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

use App\Db\Connection;

final class CalendlyStatsRepository
{
    public function __construct(private readonly Connection $db) {}

    public function counts(): array
    {
        return [
            'total' => (int) $this->db->scalar('SELECT COUNT(*) FROM calendly_appointments'),
            'scheduled' => (int) $this->db->scalar("SELECT COUNT(*) FROM calendly_appointments WHERE status = 'scheduled'"),
            'completed' => (int) $this->db->scalar("SELECT COUNT(*) FROM calendly_appointments WHERE status = 'completed'"),
            'cancelled' => (int) $this->db->scalar("SELECT COUNT(*) FROM calendly_appointments WHERE status = 'cancelled'"),
        ];
    }

    public function typeCounts(): array
    {
        $pattern = "event_type LIKE '%visio%' OR event_type LIKE '%video%' OR event_type LIKE '%Visio%' OR event_type LIKE '%Video%'";
        return [
            'visio' => (int) $this->db->scalar("SELECT COUNT(*) FROM calendly_appointments WHERE {$pattern}"),
            'phone' => (int) $this->db->scalar("SELECT COUNT(*) FROM calendly_appointments WHERE NOT ({$pattern})"),
        ];
    }

    public function weekly(): array
    {
        return $this->db->query(
            "SELECT TO_CHAR(start_time, 'IYYY-IW') as week, COUNT(*) as count, EXTRACT(YEAR FROM start_time)::TEXT as year, EXTRACT(WEEK FROM start_time)::TEXT as week_num
             FROM calendly_appointments WHERE start_time >= NOW() - INTERVAL '4 weeks' GROUP BY week, year, week_num ORDER BY week",
        );
    }

    public function monthly(): array
    {
        return $this->db->query(
            "SELECT TO_CHAR(start_time, 'YYYY-MM') as month, COUNT(*) as count, EXTRACT(YEAR FROM start_time)::TEXT as year, EXTRACT(MONTH FROM start_time)::TEXT as month_num
             FROM calendly_appointments WHERE start_time >= NOW() - INTERVAL '6 months' GROUP BY month, year, month_num ORDER BY month",
        );
    }

    public function thisMonthCount(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM calendly_appointments WHERE TO_CHAR(start_time, 'YYYY-MM') = TO_CHAR(NOW(), 'YYYY-MM')");
    }

    public function lastMonthCount(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM calendly_appointments WHERE TO_CHAR(start_time, 'YYYY-MM') = TO_CHAR(NOW() - INTERVAL '1 month', 'YYYY-MM')");
    }
}
