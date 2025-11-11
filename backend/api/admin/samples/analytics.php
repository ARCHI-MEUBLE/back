<?php
/**
 * API Admin: Analytics pour les échantillons
 * GET /api/admin/samples/analytics - Statistiques sur les échantillons
 */

require_once __DIR__ . '/../../../config/cors.php';

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

require_once __DIR__ . '/../../../core/Database.php';

try {
    $db = Database::getInstance();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 1. Nombre total d'échantillons dans les commandes
        $totalOrderedQuery = "SELECT COUNT(*) as count FROM order_sample_items";
        $totalOrdered = $db->queryOne($totalOrderedQuery);
        $totalOrderedCount = $totalOrdered['count'] ?? 0;

        // 2. Nombre de commandes contenant des échantillons
        $ordersWithSamplesQuery = "SELECT COUNT(DISTINCT order_id) as count FROM order_sample_items";
        $ordersWithSamples = $db->queryOne($ordersWithSamplesQuery);
        $ordersWithSamplesCount = $ordersWithSamples['count'] ?? 0;

        // 3. Échantillons actuellement dans les paniers
        $samplesInCartQuery = "SELECT COUNT(*) as count FROM cart_sample_items";
        $samplesInCart = $db->queryOne($samplesInCartQuery);
        $samplesInCartCount = $samplesInCart['count'] ?? 0;

        // 4. Top 5 des échantillons les plus commandés
        $topSamplesQuery = "
            SELECT
                sample_name,
                material,
                COUNT(*) as order_count
            FROM order_sample_items
            GROUP BY sample_name, material
            ORDER BY order_count DESC
            LIMIT 5
        ";
        $topSamples = $db->query($topSamplesQuery, []);

        // 5. Répartition par matériau
        $materialDistributionQuery = "
            SELECT
                material,
                COUNT(*) as count
            FROM order_sample_items
            GROUP BY material
            ORDER BY count DESC
        ";
        $materialDistribution = $db->query($materialDistributionQuery, []);

        // 6. Évolution des commandes d'échantillons (derniers 30 jours)
        $recentOrdersQuery = "
            SELECT
                DATE(o.created_at) as date,
                COUNT(DISTINCT o.id) as order_count
            FROM orders o
            JOIN order_sample_items osi ON o.id = osi.order_id
            WHERE o.created_at >= DATE('now', '-30 days')
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC
        ";
        $recentOrders = $db->query($recentOrdersQuery, []);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'analytics' => [
                'total_ordered' => $totalOrderedCount,
                'orders_with_samples' => $ordersWithSamplesCount,
                'samples_in_cart' => $samplesInCartCount,
                'top_samples' => $topSamples,
                'material_distribution' => $materialDistribution,
                'recent_orders' => $recentOrders
            ]
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);

} catch (Exception $e) {
    error_log("[ADMIN SAMPLES ANALYTICS] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur serveur',
        'message' => $e->getMessage()
    ]);
}
