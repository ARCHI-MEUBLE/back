<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

use App\Domain\Shared\DomainException;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\Calendly\LegacyCalendlyEmailGateway;
use DateTime;
use DateTimeZone;
use Throwable;

final class CalendlyWebhookRoutes
{
    public function __construct(
        private readonly CalendlyAppointmentRepository $appointments,
        private readonly LegacyCalendlyEmailGateway $mail,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('calendly/webhook')->post(null, fn(Request $r): Response => $this->handle($r));
    }

    private function handle(Request $r): Response
    {
        $event = json_decode($r->rawBody(), true);
        if (!is_array($event)) {
            throw new DomainException('Invalid JSON');
        }
        $type = $event['event'] ?? null;
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        return match ($type) {
            'invitee.created' => $this->created($payload),
            'invitee.canceled' => Response::json(['success' => true, 'message' => 'Annulation enregistrée']),
            default => Response::json(['success' => true, 'message' => 'Event logged successfully']),
        };
    }

    private function created(array $payload): Response
    {
        $name = $payload['name'] ?? 'N/A';
        $email = $payload['email'] ?? 'N/A';
        $eventType = $payload['event_type_name'] ?? 'N/A';
        $startTime = $payload['scheduled_event']['start_time'] ?? 'N/A';
        $endTime = $payload['scheduled_event']['end_time'] ?? 'N/A';
        $timezone = $payload['timezone'] ?? 'Europe/Paris';
        $calendlyEventId = $payload['event']['uri'] ?? uniqid('calendly_', true);
        $start = new DateTime($startTime);
        $end = new DateTime($endTime);
        $start->setTimezone(new DateTimeZone($timezone));
        $end->setTimezone(new DateTimeZone($timezone));
        $formattedStart = $start->format('d/m/Y à H:i');
        $formattedEnd = $end->format('H:i');
        [$configUrl, $notes] = CalendlyQuestionExtractor::extract($payload['questions_and_answers'] ?? []);
        $this->appointments->upsertFromWebhook([
            'calendly_event_id' => $calendlyEventId, 'client_name' => $name, 'client_email' => $email, 'event_type' => $eventType,
            'start_time' => $startTime, 'end_time' => $endTime, 'timezone' => $timezone, 'config_url' => $configUrl, 'additional_notes' => $notes,
        ]);
        try {
            $this->mail->sendConfirmation($email, $name, $eventType, $formattedStart, $formattedEnd, $configUrl);
        } catch (Throwable) {
        }
        try {
            $this->mail->sendAdminNotification($name, $email, $eventType, $formattedStart, $formattedEnd, $configUrl, $notes);
        } catch (Throwable) {
        }
        return Response::json(['success' => true, 'message' => 'Rendez-vous enregistré avec succès', 'data' => [
            'name' => $name, 'email' => $email, 'event_type' => $eventType, 'start_time' => $formattedStart, 'end_time' => $formattedEnd,
            'config_url' => $configUrl !== '' ? $configUrl : null, 'timezone' => $timezone,
        ]]);
    }
}
