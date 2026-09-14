<?php

declare(strict_types=1);

namespace Tests\Contract\Calendly;

use Tests\Support\ContractTestCase;

final class CalendlyTest extends ContractTestCase
{
    public function testAppointmentsForAdmin(): void
    {
        $admin = $this->admin();
        $list = $admin->get('/backend/api/calendly/appointments.php');
        $this->assertSnapshot('calendly.appointments', $list);
        self::assertNotEmpty($list->data()['appointments']);
        $this->assertSnapshot('calendly.appointments.filtered', $admin->get('/backend/api/calendly/appointments.php?status=scheduled&limit=1&offset=0'));
        $stats = $admin->get('/backend/api/calendly/appointments-stats.php');
        $this->assertSnapshot('calendly.stats', $stats);
        self::assertArrayHasKey('stats', $stats->data());
    }

    public function testAppointmentsRequireAdmin(): void
    {
        $this->assertSnapshot('calendly.appointments.unauthorized', $this->client()->get('/backend/api/calendly/appointments.php'));
        $this->assertSnapshot('calendly.appointments.unauthorized', $this->client()->get('/backend/api/calendly/appointments-stats.php'));
        $this->assertSnapshot('calendly.appointments.unauthorized', $this->client()->put('/backend/api/calendly/appointment-actions.php?id=1&action=cancel'));
    }

    public function testActions(): void
    {
        $admin = $this->admin();
        $this->assertSnapshot('calendly.action.missing', $admin->put('/backend/api/calendly/appointment-actions.php'));
        $this->assertSnapshot('calendly.action.not-found', $admin->put('/backend/api/calendly/appointment-actions.php?id=999999&action=cancel'));
        $this->assertSnapshot('calendly.action.reschedule.invalid', $admin->put('/backend/api/calendly/appointment-actions.php?id=1&action=reschedule', []));
        $this->assertSnapshot('calendly.action.reschedule', $admin->put('/backend/api/calendly/appointment-actions.php?id=1&action=reschedule', ['start_time' => '2027-02-01T10:00:00Z', 'end_time' => '2027-02-01T10:30:00Z']));
        $this->assertSnapshot('calendly.action.complete', $admin->put('/backend/api/calendly/appointment-actions.php?id=1&action=complete'));
        $this->assertSnapshot('calendly.action.cancel', $admin->put('/backend/api/calendly/appointment-actions.php?id=2&action=cancel'));
        $this->assertSnapshot('calendly.action.unknown', $admin->put('/backend/api/calendly/appointment-actions.php?id=2&action=nope'));
    }

    public function testWebhook(): void
    {
        $this->assertSnapshot('calendly.webhook.invalid', $this->client()->raw('POST', '/backend/api/calendly/webhook.php', 'not json', ['Content-Type: application/json']));
        $this->assertSnapshot('calendly.webhook.ignored', $this->client()->post('/backend/api/calendly/webhook.php', ['event' => 'invitee.canceled', 'payload' => []]));
        $created = $this->client()->post('/backend/api/calendly/webhook.php', [
            'event' => 'invitee.created',
            'payload' => [
                'name' => 'Webhook Client',
                'email' => 'webhook@example.test',
                'event_type_name' => 'Consultation',
                'timezone' => 'Europe/Paris',
                'event' => ['uri' => 'https://api.calendly.com/scheduled_events/evt-' . bin2hex(random_bytes(3))],
                'scheduled_event' => ['start_time' => '2027-03-01T09:00:00Z', 'end_time' => '2027-03-01T09:30:00Z'],
                'questions_and_answers' => [['question' => 'Lien de configuration', 'answer' => 'https://archimeuble.com/configurator/1']],
            ],
        ]);
        $this->assertSnapshot('calendly.webhook.created', $created);
    }

    public function testConfirmationAndReminders(): void
    {
        $this->assertSnapshot('calendly.confirmation.invalid', $this->client()->post('/backend/api/calendly/send-confirmation.php', []));
        $this->assertSnapshot('calendly.confirmation.unconfigured', $this->client()->post('/backend/api/calendly/send-confirmation.php', ['invitee_uri' => 'https://api.calendly.com/invitees/x', 'event_uri' => 'https://api.calendly.com/scheduled_events/y']));
        $this->assertSnapshot('calendly.reminders.forbidden', $this->client()->get('/backend/api/calendly/trigger-reminders.php?token=wrong'));
        $this->assertSnapshot('calendly.reminders', $this->client()->get('/backend/api/calendly/trigger-reminders.php?token=test-cron-secret'));
    }
}
