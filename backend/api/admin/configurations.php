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
    error_log("🔍 Admin configurations API appelée");
    error_log("👤 Session admin_email: " . (isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'NON DEFINI'));

    $db = Database::getInstance();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['id'])) {
            // Détails d'une configuration
            $id = (int)$_GET['id'];
            $query = "SELECT c.*,
                             cust.email as customer_email,
                             cust.first_name as customer_first_name,
                             cust.last_name as customer_last_name,
                             cust.phone as customer_phone,
                             m.name as model_name
                      FROM configurations c
                      LEFT JOIN customers cust ON CAST(c.user_id AS INTEGER) = cust.id
                      LEFT JOIN models m ON c.template_id = m.id
                      WHERE c.id = ?";
            $config = $db->queryOne($query, [$id]);

            if (!$config) {
                http_response_code(404);
                echo json_encode(['error' => 'Configuration introuvable']);
                exit;
            }

            // Ajouter champ customer_name combiné
            if (isset($config['customer_first_name']) && isset($config['customer_last_name'])) {
                $config['customer_name'] = trim($config['customer_first_name'] . ' ' . $config['customer_last_name']);
            }

            http_response_code(200);
            echo json_encode(['configuration' => $config]);
            exit;
        }

        // Liste paginée
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        // Filtre par statut si fourni
        $statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
        $whereClause = '';
        if ($statusFilter && in_array($statusFilter, ['en_attente_validation', 'validee', 'payee', 'en_production', 'livree'])) {
            $whereClause = "WHERE c.status = '$statusFilter'";
        }

        // SQLite a des problèmes avec les paramètres PDO sur LIMIT/OFFSET
        // On les caste en int pour la sécurité et on les interpole directement
        $listQuery = "SELECT c.id, c.user_id, c.template_id, c.prompt, c.config_string, c.price, c.glb_url, c.dxf_url, c.created_at, c.status,
                              cust.email as customer_email,
                              cust.first_name as customer_first_name,
                              cust.last_name as customer_last_name,
                              cust.phone as customer_phone,
                              m.name as model_name
                       FROM configurations c
                       LEFT JOIN customers cust ON CAST(c.user_id AS INTEGER) = cust.id
                       LEFT JOIN models m ON c.template_id = m.id
                       $whereClause
                       ORDER BY c.created_at DESC
                       LIMIT $limit OFFSET $offset";

        error_log("📝 Requête SQL: " . $listQuery);
        $rows = $db->query($listQuery);
        error_log("🔍 Résultat query(): " . print_r($rows, true));

        error_log("📊 Nombre de configurations trouvées: " . count($rows));

        // Ajouter customer_name combiné pour chaque ligne
        foreach ($rows as &$row) {
            if (isset($row['customer_first_name']) && isset($row['customer_last_name'])) {
                $row['customer_name'] = trim($row['customer_first_name'] . ' ' . $row['customer_last_name']);
            }
        }

        $countQuery = "SELECT COUNT(*) as count FROM configurations c $whereClause";
        $countRow = $db->queryOne($countQuery);
        $total = $countRow ? (int)$countRow['count'] : 0;

        error_log("✅ Envoi de " . count($rows) . " configurations (total: $total)");

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
