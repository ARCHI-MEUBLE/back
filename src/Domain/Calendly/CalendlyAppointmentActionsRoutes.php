<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class CalendlyAppointmentActionsRoutes
{
    public function __construct(private readonly CalendlyAppointmentRepository $appointments) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('calendly/appointment-actions', [new AdminGuard()])->put(null, fn(Request $r): Response => $this->apply($r));
    }

    private function apply(Request $r): Response
    {
        $id = $r->queryString('id');
        $action = $r->queryString('action');
        if ($id === null || $action === null) {
            throw new DomainException('ID ou action manquant');
        }
        $appointmentId = (int) $id;
        if ($this->appointments->findById($appointmentId) === null) {
            throw new NotFoundException('Rendez-vous introuvable');
        }
        return match ($action) {
            'cancel' => $this->setStatus($appointmentId, 'cancelled', 'Rendez-vous annulé avec succès'),
            'complete' => $this->setStatus($appointmentId, 'completed', 'Rendez-vous marqué comme terminé'),
            'reschedule' => $this->reschedule($r, $appointmentId),
            default => throw new DomainException('Action invalide. Actions supportées: cancel, complete, reschedule'),
        };
    }

    private function setStatus(int $id, string $status, string $message): Response
    {
        $this->appointments->setStatus($id, $status);
        return Response::json(['success' => true, 'message' => $message, 'new_status' => $status]);
    }

    private function reschedule(Request $r, int $id): Response
    {
        $data = $r->jsonOrEmpty();
        if (!isset($data['start_time']) || !isset($data['end_time'])) {
            throw new DomainException('Nouvelles dates manquantes');
        }
        $this->appointments->reschedule($id, (string) $data['start_time'], (string) $data['end_time']);
        return Response::json(['success' => true, 'message' => 'Rendez-vous reprogrammé avec succès', 'new_status' => 'scheduled', 'new_start_time' => $data['start_time'], 'new_end_time' => $data['end_time']]);
    }
}
