<?php

declare(strict_types=1);

namespace App\Domain\Calendly;

use DateTime;
use Throwable;

final class CalendlyAppointmentMapper
{
    public static function withFormattedDates(array $appointment): array
    {
        try {
            $start = new DateTime($appointment['start_time']);
            $end = new DateTime($appointment['end_time']);
            $appointment['formatted_start'] = $start->format('d/m/Y à H:i');
            $appointment['formatted_end'] = $end->format('H:i');
            $appointment['formatted_date'] = $start->format('d/m/Y');
            $appointment['formatted_time'] = $start->format('H:i') . ' - ' . $end->format('H:i');
        } catch (Throwable) {
            $appointment['formatted_start'] = $appointment['start_time'];
            $appointment['formatted_end'] = $appointment['end_time'];
        }
        return $appointment;
    }
}
