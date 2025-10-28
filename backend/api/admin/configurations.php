<?php
/**
 * API Admin: Configurations clients
 * GET /api/admin/configurations - Lister toutes les configurations
 * GET /api/admin/configurations?id=123 - Détails d'une configuration
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

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['id'])) {
            // Détails d'une configuration
            $id = (int)$_GET['id'];
            $query = "SELECT sc.*, c.email as customer_email, c.first_name, c.last_name, m.name as model_name
                      FROM saved_configurations sc
                      LEFT JOIN customers c ON sc.customer_id = c.id
                      LEFT JOIN models m ON sc.model_id = m.id
                      WHERE sc.id = ?";
            $config = $db->queryOne($query, [$id]);

            if (!$config) {
                http_response_code(404);
                echo json_encode(['error' => 'Configuration introuvable']);
                exit;
            }

            http_response_code(200);
            echo json_encode(['configuration' => $config]);
            exit;
        }

        // Liste paginée
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $listQuery = "SELECT sc.id, sc.customer_id, sc.model_id, sc.name, sc.prompt, sc.config_data, sc.price, sc.glb_url, sc.thumbnail_url, sc.created_at,
                              c.email as customer_email, c.first_name, c.last_name,
                              m.name as model_name
                       FROM saved_configurations sc
                       LEFT JOIN customers c ON sc.customer_id = c.id
                       LEFT JOIN models m ON sc.model_id = m.id
                       ORDER BY sc.created_at DESC
                       LIMIT ? OFFSET ?";
        $rows = $db->query($listQuery, [$limit, $offset]);

        $countRow = $db->queryOne("SELECT COUNT(*) as count FROM saved_configurations");
        $total = $countRow ? (int)$countRow['count'] : 0;

        http_response_code(200);
        echo json_encode([
            'configurations' => $rows,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
