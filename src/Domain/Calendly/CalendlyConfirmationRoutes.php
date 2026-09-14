<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

use App\Domain\Notification\AdminNotificationRepository;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;
use App\Infrastructure\Calendly\CalendlyApiClient;
use App\Infrastructure\Calendly\LegacyCalendlyEmailGateway;
use DateTime;
use DateTimeZone;
use Throwable;

final class CalendlyConfirmationRoutes
{
    public function __construct(
        private readonly CalendlyAppointmentRepository $appointments,
        private readonly AdminNotificationRepository $adminNotifications,
        private readonly LegacyCalendlyEmailGateway $mail,
        private readonly ?string $calendlyApiToken,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('calendly/send-confirmation')->post(null, fn(Request $r): Response => $this->send($r));
    }

    private function send(Request $r): Response
    {
        $raw = $r->rawBody();
        $data = json_decode($raw, true);
        if (!is_array($data) || $data === []) {
            return Response::json(['error' => 'Invalid JSON', 'received' => $raw], 400);
        }
        if (!isset($data['invitee_uri']) || $data['invitee_uri'] === '') {
            return Response::json(['error' => 'Missing invitee_uri', 'received_data' => array_keys($data)], 400);
        }
        if ($this->calendlyApiToken === null || trim($this->calendlyApiToken) === '') {
            return Response::json(['error' => 'Calendly API token not configured', 'message' => 'Veuillez configurer CALENDLY_API_TOKEN dans le fichier .env', 'instructions' => 'Obtenez votre token sur: https://calendly.com/integrations/api_webhooks'], 500);
        }
        $client = new CalendlyApiClient($this->calendlyApiToken);
        $invitee = $this->fetchResource($client, (string) $data['invitee_uri'], 'invitee data from Calendly');
        if ($invitee instanceof Response) {
            return $invitee;
        }
        $name = $invitee['name'] ?? 'Client';
        $email = $invitee['email'] ?? '';
        $eventUri = $invitee['event'] ?? '';
        $timezone = $invitee['timezone'] ?? 'Europe/Paris';
        $phone = (string) ($invitee['text_reminder_number'] ?? '');
        if ($email === '') {
            return Response::json(['error' => 'No email found in Calendly invitee data'], 400);
        }
        if ($eventUri === '') {
            return Response::json(['error' => 'No event URI found in invitee data'], 400);
        }
        $event = $this->fetchResource($client, (string) $eventUri, 'event data from Calendly');
        if ($event instanceof Response) {
            return $event;
        }
        $eventType = $event['name'] ?? 'Rendez-vous';
        $startTime = $event['start_time'] ?? '';
        $endTime = $event['end_time'] ?? '';
        $meetingUrl = $event['location']['join_url'] ?? null;
        if ($startTime === '' || $endTime === '') {
            return Response::json(['error' => 'Missing event times in Calendly data'], 400);
        }
        $qa = is_array($invitee['questions_and_answers'] ?? null) ? $invitee['questions_and_answers'] : [];
        [$configUrl, $notes] = CalendlyQuestionExtractor::extract($qa);
        $phone = CalendlyQuestionExtractor::extractPhone($qa, $phone);
        $isPhoneAppointment = stripos($eventType, 'téléphone') !== false || stripos($eventType, 'phone') !== false || stripos($eventType, 'appel') !== false;
        try {
            $start = new DateTime($startTime);
            $end = new DateTime($endTime);
            $start->setTimezone(new DateTimeZone($timezone));
            $end->setTimezone(new DateTimeZone($timezone));
        } catch (Throwable) {
            return Response::json(['error' => 'Invalid date format'], 400);
        }
        $formattedStart = $start->format('d/m/Y à H:i');
        $formattedEnd = $end->format('H:i');
        $appointmentId = $this->appointments->upsertFromWebhook([
            'calendly_event_id' => $data['invitee_uri'], 'client_name' => $name, 'client_email' => $email, 'event_type' => $eventType,
            'start_time' => $startTime, 'end_time' => $endTime, 'timezone' => $timezone, 'config_url' => $configUrl, 'additional_notes' => $notes,
            'meeting_url' => $meetingUrl, 'phone_number' => $phone !== '' ? $phone : null,
        ]);
        return $this->notify($appointmentId, $name, $email, $phone, $eventType, $formattedStart, $formattedEnd, $configUrl, $notes, $isPhoneAppointment);
    }

    private function fetchResource(CalendlyApiClient $client, string $uri, string $label): Response|array
    {
        [$status, $body] = $client->get($uri);
        if ($status !== 200) {
            return Response::json(['error' => "Failed to fetch {$label}", 'http_code' => $status], 500);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['resource']) || !is_array($decoded['resource'])) {
            return Response::json(['error' => 'Invalid response from Calendly API'], 500);
        }
        return $decoded['resource'];
    }

    private function notify(int $appointmentId, string $name, string $email, string $phone, string $eventType, string $start, string $end, string $configUrl, string $notes, bool $isPhoneAppointment): Response
    {
        try {
            $clientSent = $this->mail->sendConfirmation($email, $name, $eventType, $start, $end, $configUrl);
            $fullNotes = $notes . ($isPhoneAppointment && $phone !== '' ? "\nNuméro de téléphone: {$phone}" : '');
            $adminSent = $this->mail->sendAdminNotification($name, $email, $eventType, $start, $end, $configUrl, $fullNotes);
            try {
                $type = $isPhoneAppointment ? 'calendly_phone' : 'calendly_video';
                $message = sprintf('Nouveau rendez-vous %s - %s (%s) prévu le %s', $isPhoneAppointment ? 'téléphonique' : 'visio', $name, $email, $start);
                $this->adminNotifications->notifyAllAdmins($type, $message, $appointmentId);
            } catch (Throwable) {
            }
            return Response::json(['success' => true, 'message' => 'Emails de confirmation envoyés', 'data' => [
                'name' => $name, 'email' => $email, 'phone_number' => $phone !== '' ? $phone : null, 'event_type' => $eventType,
                'start_time' => $start, 'end_time' => $end, 'is_phone_appointment' => $isPhoneAppointment,
                'client_email_sent' => $clientSent, 'admin_email_sent' => $adminSent,
            ]]);
        } catch (Throwable $e) {
            return Response::json(['success' => true, 'message' => 'Rendez-vous enregistré (emails non envoyés - SMTP non configuré)', 'data' => [
                'name' => $name, 'email' => $email, 'event_type' => $eventType, 'start_time' => $start, 'emails_sent' => false, 'email_error' => $e->getMessage(),
            ]]);
        }
    }
}
