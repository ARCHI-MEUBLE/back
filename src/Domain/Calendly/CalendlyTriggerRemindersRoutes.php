<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class CalendlyTriggerRemindersRoutes
{
    public function __construct(
        private readonly CalendlyReminderService $reminders,
        private readonly ?string $cronSecret,
    ) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('calendly/trigger-reminders')->get(null, fn(Request $r): Response => $this->trigger($r));
    }

    private function trigger(Request $r): Response
    {
        if ($this->cronSecret === null || $this->cronSecret === '') {
            return Response::json(['success' => false, 'error' => 'CRON_SECRET not configured', 'message' => "Le token secret n'est pas configuré dans les variables d'environnement"], 500);
        }
        if (($r->queryString('token') ?? '') !== $this->cronSecret) {
            return Response::json(['success' => false, 'error' => 'Unauthorized', 'message' => 'Token invalide ou manquant'], 403);
        }
        $output = implode("\n", $this->reminders->run());
        return Response::json(['success' => true, 'message' => 'Reminder check completed', 'timestamp' => date('Y-m-d H:i:s'), 'output' => $output]);
    }
}
