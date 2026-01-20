<?php
/**
 * API Admin: Configurations clients
 * GET /api/admin/configurations - Lister toutes les configurations
 * GET /api/admin/configurations?id=123 - Détails d'une configuration
 *
 * SÉCURITÉ: Requêtes préparées pour prévenir l'injection SQL
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Session.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification admin
$session = Session::getInstance();
if (!$session->has('admin_email') || $session->get('is_admin') !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['id'])) {
            // SÉCURITÉ: Validation stricte de l'ID
            $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID invalide']);
                exit;
            }

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

        // Liste paginée - SÉCURITÉ: Validation des paramètres
        $limit = filter_var($_GET['limit'] ?? 100, FILTER_VALIDATE_INT);
        $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);

        // Limites de sécurité
        $limit = ($limit === false || $limit <= 0 || $limit > 500) ? 100 : $limit;
        $offset = ($offset === false || $offset < 0) ? 0 : $offset;

        // SÉCURITÉ: Liste blanche stricte des statuts + requête préparée
        $validStatuses = ['en_attente_validation', 'validee', 'payee', 'en_production', 'livree', 'annulee', 'en_commande'];
        $statusFilter = isset($_GET['status']) ? $_GET['status'] : null;

        $params = [];
        $whereClause = '';

        if ($statusFilter !== null) {
            // SÉCURITÉ: Vérification stricte contre la liste blanche
            if (!in_array($statusFilter, $validStatuses, true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Statut invalide']);
                exit;
            }
            $whereClause = "WHERE c.status = ?";
            $params[] = $statusFilter;
        }

        // SÉCURITÉ: Requête préparée avec paramètres
        $listQuery = "SELECT c.id, c.user_id, c.template_id as model_id, c.prompt, c.config_string, c.price, c.glb_url, c.dxf_url, c.created_at, c.status,
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
                       LIMIT ? OFFSET ?";

        // Ajouter limit et offset aux paramètres
        $params[] = $limit;
        $params[] = $offset;

        $rows = $db->query($listQuery, $params);

        // Enrichir les données (nom de la configuration, nom complet du client)
        foreach ($rows as &$row) {
            if (isset($row['customer_first_name']) || isset($row['customer_last_name'])) {
                $row['customer_name'] = trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? ''));
            }

            // Extraire le nom de la configuration du JSON
            if (isset($row['config_string'])) {
                try {
                    $configData = json_decode($row['config_string'], true);
                    if (isset($configData['name'])) {
                        $row['name'] = $configData['name'];
                    }
                } catch (Exception $e) {
                    // Ignorer les erreurs de parsing
                }
            }
        }

        // SÉCURITÉ: Requête count aussi préparée
        $countParams = $statusFilter ? [$statusFilter] : [];
        $countQuery = "SELECT COUNT(*) as count FROM configurations c $whereClause";
        $countRow = $db->queryOne($countQuery, $countParams);
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
    // SÉCURITÉ: Ne pas exposer les détails de l'erreur
    error_log("[ADMIN CONFIG ERROR] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
