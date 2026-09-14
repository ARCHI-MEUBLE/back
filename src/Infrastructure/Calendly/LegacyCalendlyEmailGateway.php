<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendly;

final class LegacyCalendlyEmailGateway
{
    public function __construct(private readonly string $rootDir) {}

    public function sendConfirmation(string $email, string $name, string $eventType, string $start, string $end, string $configUrl, ?string $meetingUrl = null): bool
    {
        return (bool) $this->client()->sendConfirmationEmail($email, $name, $eventType, $start, $end, $configUrl, $meetingUrl);
    }

    public function sendAdminNotification(string $name, string $email, string $eventType, string $start, string $end, string $configUrl, string $notes): bool
    {
        return (bool) $this->client()->sendAdminNotification($name, $email, $eventType, $start, $end, $configUrl, $notes);
    }

    public function sendReminder(string $email, string $name, string $eventType, string $start, string $end, string $configUrl): bool
    {
        return (bool) $this->client()->sendReminderEmail($email, $name, $eventType, $start, $end, $configUrl);
    }

    private function client(): \CalendlyEmailService
    {
        require_once $this->rootDir . '/legacy/calendly/EmailService.php';
        return new \CalendlyEmailService();
    }
}
