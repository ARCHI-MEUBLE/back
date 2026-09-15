<?php

declare(strict_types=1);

namespace App;

use App\Config\Settings;
use App\Db\Connection;
use App\Domain\Calendly\CalendlyAppointmentActionsRoutes;
use App\Domain\Calendly\CalendlyAppointmentRepository;
use App\Domain\Calendly\CalendlyAppointmentsRoutes;
use App\Domain\Calendly\CalendlyConfirmationRoutes;
use App\Domain\Calendly\CalendlyReminderService;
use App\Domain\Calendly\CalendlyStatsRepository;
use App\Domain\Calendly\CalendlyStatsRoutes;
use App\Domain\Calendly\CalendlyTriggerRemindersRoutes;
use App\Domain\Calendly\CalendlyWebhookRoutes;
use App\Domain\Notification\AdminNotificationRepository;
use App\Http\RouteCollection;
use App\Infrastructure\Calendly\LegacyCalendlyEmailGateway;

final class CalendlyRouteRegistry
{
    public static function register(RouteCollection $routes, Settings $settings, Connection $db): void
    {
        $appointments = new CalendlyAppointmentRepository($db);
        $adminNotifications = new AdminNotificationRepository($db);
        $mail = new LegacyCalendlyEmailGateway($settings->rootDir);
        (new CalendlyAppointmentsRoutes($appointments))->register($routes);
        (new CalendlyStatsRoutes(new CalendlyStatsRepository($db)))->register($routes);
        (new CalendlyAppointmentActionsRoutes($appointments))->register($routes);
        (new CalendlyWebhookRoutes($appointments, $mail))->register($routes);
        (new CalendlyConfirmationRoutes($appointments, $adminNotifications, $mail, $settings->calendlyApiToken))->register($routes);
        (new CalendlyTriggerRemindersRoutes(new CalendlyReminderService($appointments, $mail), $settings->cronSecret))->register($routes);
    }
}
