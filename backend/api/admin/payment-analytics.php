<?php
/**
 * API Admin: Analytics des paiements
 * GET /api/admin/payment-analytics?period=30d
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
    $period = $_GET['period'] ?? '30d';

    // Convertir la période en nombre de jours
    $days = match($period) {
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '1y' => 365,
        default => 30
    };

    $dateFrom = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

    // KPIs: Revenu total
    $revenueQuery = "SELECT
        SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_revenue,
        COUNT(*) as total_orders,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payments,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as successful_payments,
        COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed_payments
    FROM orders
    WHERE created_at >= ?";

    $kpis = $db->queryOne($revenueQuery, [$dateFrom]);

    // Calculer le panier moyen
    $avgOrderValue = $kpis['successful_payments'] > 0
        ? $kpis['total_revenue'] / $kpis['successful_payments']
        : 0;

    // Revenu par période (par jour pour 7d/30d, par mois pour 90d/1y)
    $groupByDay = in_array($period, ['7d', '30d']);

    if ($groupByDay) {
        // Grouper par jour pour les périodes courtes
        $revenueByPeriodQuery = "SELECT
            TO_CHAR(created_at, 'YYYY-MM-DD') as period,
            SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as revenue
        FROM orders
        WHERE created_at >= ?
        GROUP BY period
        ORDER BY period ASC";

        $revenueByPeriod = $db->query($revenueByPeriodQuery, [$dateFrom]);

        // Créer un index des données existantes
        $revenueByDay = [];
        foreach ($revenueByPeriod as $row) {
            $revenueByDay[$row['period']] = (float) $row['revenue'];
        }

        // Générer tous les jours de la période (même ceux sans données)
        $formattedRevenueByMonth = [];
        $currentDate = new DateTime($dateFrom);
        $endDate = new DateTime('now');

        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayLabel = $currentDate->format('d/m');
            $revenue = $revenueByDay[$dateKey] ?? 0;

            $formattedRevenueByMonth[] = [
                'month' => $dayLabel,
                'revenue' => $revenue
            ];

            $currentDate->modify('+1 day');
        }
    } else {
        // Grouper par mois pour les périodes longues
        $monthsCount = match($period) {
            '90d' => 3,
            '1y' => 12,
            default => 3
        };

        $revenueByMonthQuery = "SELECT
            TO_CHAR(created_at, 'YYYY-MM') as month,
            SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as revenue
        FROM orders
        WHERE created_at >= NOW() - INTERVAL '{$monthsCount} months'
        GROUP BY month
        ORDER BY month ASC";

        $revenueByMonth = $db->query($revenueByMonthQuery);

        // Créer un index des données existantes
        $revenueByMonthData = [];
        foreach ($revenueByMonth as $row) {
            $revenueByMonthData[$row['month']] = (float) $row['revenue'];
        }

        // Formater les mois en français
        $monthsMap = [
            '01' => 'Jan', '02' => 'Fév', '03' => 'Mar', '04' => 'Avr',
            '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Aoû',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Déc'
        ];

        // Générer tous les mois de la période (même ceux sans données)
        $formattedRevenueByMonth = [];
        $currentDate = new DateTime();
        $currentDate->modify("-{$monthsCount} months");

        for ($i = 0; $i <= $monthsCount; $i++) {
            $monthKey = $currentDate->format('Y-m');
            $monthNum = $currentDate->format('m');
            $monthLabel = $monthsMap[$monthNum] ?? $monthNum;
            $revenue = $revenueByMonthData[$monthKey] ?? 0;

            $formattedRevenueByMonth[] = [
                'month' => $monthLabel,
                'revenue' => $revenue
            ];

            $currentDate->modify('+1 month');
        }
    }

    // Distribution des moyens de paiement
    $paymentMethodsQuery = "SELECT
        payment_method as method,
        COUNT(*) as count,
        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders WHERE created_at >= ?)) as percentage
    FROM orders
    WHERE created_at >= ?
    GROUP BY payment_method
    ORDER BY count DESC";

    $paymentMethods = $db->query($paymentMethodsQuery, [$dateFrom, $dateFrom]);

    // Formater les méthodes de paiement en français
    $methodsMap = [
        'card' => 'Carte bancaire',
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
        'bank_transfer' => 'Virement bancaire',
        'cash' => 'Espèces',
        'check' => 'Chèque'
    ];

    $formattedPaymentMethods = [];
    foreach ($paymentMethods as $row) {
        $formattedPaymentMethods[] = [
            'method' => $methodsMap[$row['method']] ?? ucfirst($row['method']),
            'count' => (int) $row['count'],
            'percentage' => (float) $row['percentage']
        ];
    }

    // Construire la réponse
    $analytics = [
        'total_revenue' => (float) ($kpis['total_revenue'] ?? 0),
        'total_orders' => (int) ($kpis['total_orders'] ?? 0),
        'pending_payments' => (int) ($kpis['pending_payments'] ?? 0),
        'successful_payments' => (int) ($kpis['successful_payments'] ?? 0),
        'failed_payments' => (int) ($kpis['failed_payments'] ?? 0),
        'average_order_value' => (float) $avgOrderValue,
        'revenue_by_month' => $formattedRevenueByMonth,
        'payment_methods_distribution' => $formattedPaymentMethods
    ];

    http_response_code(200);
    echo json_encode($analytics);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
