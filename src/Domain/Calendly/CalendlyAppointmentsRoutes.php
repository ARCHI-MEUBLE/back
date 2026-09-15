<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

use App\Http\Guard\AdminGuard;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class CalendlyAppointmentsRoutes
{
    public function __construct(private readonly CalendlyAppointmentRepository $appointments) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('calendly/appointments', [new AdminGuard()])->get(null, fn(Request $r): Response => $this->list($r));
    }

    private function list(Request $r): Response
    {
        $status = $r->queryString('status');
        $limit = (int) ($r->queryString('limit') ?? 50);
        $offset = (int) ($r->queryString('offset') ?? 0);
        return Response::json([
            'success' => true,
            'appointments' => $this->appointments->all($status, $limit, $offset),
            'total' => $this->appointments->count($status),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
