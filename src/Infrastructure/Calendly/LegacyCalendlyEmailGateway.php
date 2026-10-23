<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendly;

use App\Lib\Html;

final class LegacyCalendlyEmailGateway
{
    public function __construct(private readonly string $rootDir) {}

    public function sendConfirmation(string $email, string $name, string $eventType, string $start, string $end, string $configUrl, ?string $meetingUrl = null): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        return (bool) $this->client()->sendConfirmationEmail($email, Html::e($name), Html::e($eventType), Html::e($start), Html::e($end), self::link($configUrl), $meetingUrl === null ? null : self::link($meetingUrl));
    }

    public function sendAdminNotification(string $name, string $email, string $eventType, string $start, string $end, string $configUrl, string $notes): bool
    {
        return (bool) $this->client()->sendAdminNotification(Html::e($name), Html::e($email), Html::e($eventType), Html::e($start), Html::e($end), self::link($configUrl), Html::e($notes));
    }

    public function sendReminder(string $email, string $name, string $eventType, string $start, string $end, string $configUrl): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        return (bool) $this->client()->sendReminderEmail($email, Html::e($name), Html::e($eventType), Html::e($start), Html::e($end), self::link($configUrl));
    }

    private static function link(string $url): string
    {
        return preg_match('#^https://#i', trim($url)) === 1 ? Html::e(trim($url)) : '';
    }

    private function client(): \CalendlyEmailService
    {
        require_once $this->rootDir . '/legacy/calendly/EmailService.php';
        return new \CalendlyEmailService();
    }
}
