<?php
/**
 * API Admin: Statistiques des rendez-vous
 * GET /api/calendly/appointments-stats.php
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// L'authentification est gérée par Next.js API route
// Pas besoin de vérifier $_SESSION ici

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();

    // Statistiques globales
    $totalAppointments = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments"
    )['count'];

    $scheduledCount = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments WHERE status = 'scheduled'"
    )['count'];

    $completedCount = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments WHERE status = 'completed'"
    )['count'];

    $cancelledCount = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments WHERE status = 'cancelled'"
    )['count'];

    // Taux d'annulation
    $cancellationRate = $totalAppointments > 0 ? round(($cancelledCount / $totalAppointments) * 100, 2) : 0;

    // Rendez-vous par type (visio vs téléphone)
    // On compte d'abord les visio, puis tout le reste comme téléphone
    $visioCount = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments
         WHERE event_type LIKE '%visio%' OR event_type LIKE '%video%' OR event_type LIKE '%Visio%' OR event_type LIKE '%Video%'"
    )['count'];

    // Téléphone = tous les autres rendez-vous (qui ne sont pas visio)
    $phoneCount = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments
         WHERE NOT (event_type LIKE '%visio%' OR event_type LIKE '%video%' OR event_type LIKE '%Visio%' OR event_type LIKE '%Video%')"
    )['count'];

    // Rendez-vous par semaine (4 dernières semaines)
    $weeklyStats = $db->query(
        "SELECT
            TO_CHAR(start_time, 'IYYY-IW') as week,
            COUNT(*) as count,
            EXTRACT(YEAR FROM start_time)::TEXT as year,
            EXTRACT(WEEK FROM start_time)::TEXT as week_num
         FROM calendly_appointments
         WHERE start_time >= NOW() - INTERVAL '4 weeks'
         GROUP BY week, year, week_num
         ORDER BY week"
    );

    // Rendez-vous par mois (6 derniers mois)
    $monthlyStats = $db->query(
        "SELECT
            TO_CHAR(start_time, 'YYYY-MM') as month,
            COUNT(*) as count,
            EXTRACT(YEAR FROM start_time)::TEXT as year,
            EXTRACT(MONTH FROM start_time)::TEXT as month_num
         FROM calendly_appointments
         WHERE start_time >= NOW() - INTERVAL '6 months'
         GROUP BY month, year, month_num
         ORDER BY month"
    );

    // Rendez-vous par statut (pour pie chart)
    $statusDistribution = [
        ['status' => 'scheduled', 'count' => $scheduledCount, 'label' => 'Prévus'],
        ['status' => 'completed', 'count' => $completedCount, 'label' => 'Terminés'],
        ['status' => 'cancelled', 'count' => $cancelledCount, 'label' => 'Annulés'],
    ];

    // Répartition téléphone vs visio (pour bar chart)
    $typeDistribution = [
        ['type' => 'phone', 'count' => $phoneCount, 'label' => 'Téléphone'],
        ['type' => 'visio', 'count' => $visioCount, 'label' => 'Visioconférence'],
    ];

    // KPIs du mois en cours
    $thisMonthCount = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments
         WHERE TO_CHAR(start_time, 'YYYY-MM') = TO_CHAR(NOW(), 'YYYY-MM')"
    )['count'];

    $lastMonthCount = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments
         WHERE TO_CHAR(start_time, 'YYYY-MM') = TO_CHAR(NOW() - INTERVAL '1 month', 'YYYY-MM')"
    )['count'];

    // Tendance mois en cours vs mois dernier
    $monthlyTrend = 0;
    if ($lastMonthCount > 0) {
        $monthlyTrend = round((($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 2);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => $totalAppointments,
            'scheduled' => $scheduledCount,
            'completed' => $completedCount,
            'cancelled' => $cancelledCount,
            'cancellation_rate' => $cancellationRate,
            'phone_count' => $phoneCount,
            'visio_count' => $visioCount,
            'this_month_count' => $thisMonthCount,
            'last_month_count' => $lastMonthCount,
            'monthly_trend' => $monthlyTrend,
        ],
        'charts' => [
            'weekly' => $weeklyStats,
            'monthly' => $monthlyStats,
            'status_distribution' => $statusDistribution,
            'type_distribution' => $typeDistribution,
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
?>
