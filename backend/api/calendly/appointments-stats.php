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

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

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
    $phoneCount = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments
         WHERE event_type LIKE '%téléphone%' OR event_type LIKE '%phone%' OR event_type LIKE '%appel%'"
    )['count'];

    $visioCount = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments
         WHERE event_type LIKE '%visio%' OR event_type LIKE '%video%'"
    )['count'];

    // Rendez-vous par semaine (4 dernières semaines)
    $weeklyStats = $db->query(
        "SELECT
            strftime('%Y-%W', start_time) as week,
            COUNT(*) as count,
            strftime('%Y', start_time) as year,
            strftime('%W', start_time) as week_num
         FROM calendly_appointments
         WHERE start_time >= datetime('now', '-4 weeks')
         GROUP BY week
         ORDER BY week"
    );

    // Rendez-vous par mois (6 derniers mois)
    $monthlyStats = $db->query(
        "SELECT
            strftime('%Y-%m', start_time) as month,
            COUNT(*) as count,
            strftime('%Y', start_time) as year,
            strftime('%m', start_time) as month_num
         FROM calendly_appointments
         WHERE start_time >= datetime('now', '-6 months')
         GROUP BY month
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
         WHERE strftime('%Y-%m', start_time) = strftime('%Y-%m', 'now')"
    )['count'];

    $lastMonthCount = $db->queryOne(
        "SELECT COUNT(*) as count FROM calendly_appointments
         WHERE strftime('%Y-%m', start_time) = strftime('%Y-%m', 'now', '-1 month')"
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
