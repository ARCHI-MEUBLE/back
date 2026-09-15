<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

use App\Infrastructure\Calendly\LegacyCalendlyEmailGateway;
use DateInterval;
use DateTime;
use DateTimeZone;
use Throwable;

final class CalendlyReminderService
{
    public function __construct(
        private readonly CalendlyAppointmentRepository $appointments,
        private readonly LegacyCalendlyEmailGateway $mail,
    ) {}

    public function run(): array
    {
        $lines = [];
        $sent24h = $this->processWindow('reminder_24h_sent', 'PT23H', 'PT25H', $lines, '24h');
        $sent1h = $this->processWindow('reminder_1h_sent', 'PT50M', 'PT70M', $lines, '1h');
        $lines[] = "Completed: {$sent24h['sent']}/{$sent24h['total']} 24h reminders, {$sent1h['sent']}/{$sent1h['total']} 1h reminders";
        return $lines;
    }

    private function processWindow(string $column, string $fromOffset, string $toOffset, array &$lines, string $label): array
    {
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $from = (clone $now)->add(new DateInterval($fromOffset));
        $to = (clone $now)->add(new DateInterval($toOffset));
        $due = $this->appointments->dueForReminder($column, $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'));
        $lines[] = 'Found ' . count($due) . " appointment(s) requiring {$label} reminder";
        $sent = 0;
        foreach ($due as $appointment) {
            try {
                $start = new DateTime($appointment['start_time']);
                $end = new DateTime($appointment['end_time']);
                $tz = new DateTimeZone($appointment['timezone']);
                $start->setTimezone($tz);
                $end->setTimezone($tz);
                $ok = $this->mail->sendReminder($appointment['client_email'], $appointment['client_name'], $appointment['event_type'], $start->format('d/m/Y à H:i'), $end->format('H:i'), $appointment['config_url'] ?? '');
                if ($ok) {
                    $this->appointments->markReminderSent($column, (int) $appointment['id']);
                    $sent++;
                    $lines[] = "{$label} reminder sent to {$appointment['client_name']} ({$appointment['client_email']})";
                }
            } catch (Throwable $e) {
                $lines[] = "Error sending {$label} reminder to {$appointment['client_email']}: " . $e->getMessage();
            }
        }
        return ['sent' => $sent, 'total' => count($due)];
    }
}
