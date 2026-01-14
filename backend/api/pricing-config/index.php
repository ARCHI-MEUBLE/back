<?php
/**
 * API de gestion de la configuration des prix
 * Endpoint: /backend/api/pricing-config/index.php
 *
 * Méthodes supportées:
 * - GET    : Récupérer les paramètres de prix (tous ou filtrés par category/item_type)
 * - POST   : Créer un nouveau paramètre
 * - PUT    : Mettre à jour un paramètre existant
 * - DELETE : Désactiver un paramètre (soft delete)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Obtenir la connexion PDO à la base de données
$db = Database::getInstance()->getPDO();

$method = $_SERVER['REQUEST_METHOD'];

/**
 * GET - Récupérer les paramètres de prix
 *
 * Query params:
 * - category: Filtrer par catégorie (materials, drawers, shelves, etc.)
 * - item_type: Filtrer par type d'item
 * - id: Récupérer un paramètre spécifique par ID
 * - active_only: true/false (default: true)
 */
if ($method === 'GET') {
    try {
        $category = isset($_GET['category']) ? trim($_GET['category']) : null;
        $item_type = isset($_GET['item_type']) ? trim($_GET['item_type']) : null;
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $active_only = !isset($_GET['active_only']) || $_GET['active_only'] !== 'false';

        $sql = 'SELECT * FROM pricing_config WHERE 1=1';
        $params = [];

        if ($active_only) {
            $sql .= ' AND is_active = 1';
        }

        if ($id) {
            $sql .= ' AND id = :id';
            $params[':id'] = $id;
        } elseif ($category) {
            $sql .= ' AND category = :category';
            $params[':category'] = $category;

            if ($item_type) {
                $sql .= ' AND item_type = :item_type';
                $params[':item_type'] = $item_type;
            }
        }

        $sql .= ' ORDER BY category, item_type, param_name';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si on a filtré par category et item_type, grouper les résultats par paramètre
        if ($category && $item_type && count($results) > 0) {
            $grouped = [];
            foreach ($results as $row) {
                $grouped[$row['param_name']] = [
                    'id' => $row['id'],
                    'value' => $row['param_value'],
                    'unit' => $row['unit'],
                    'description' => $row['description'],
                    'is_active' => $row['is_active']
                ];
            }
            echo json_encode(['success' => true, 'data' => $grouped]);
        } elseif ($id && count($results) === 1) {
            // Si on a récupéré par ID, retourner l'objet directement
            echo json_encode(['success' => true, 'data' => $results[0]]);
        } else {
            // Sinon, retourner le tableau complet
            echo json_encode(['success' => true, 'data' => $results]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Query failed: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * POST - Créer un nouveau paramètre de prix
 *
 * Body JSON:
 * {
 *   "category": "materials",
 *   "item_type": "oak",
 *   "param_name": "price_per_m2",
 *   "param_value": 200,
 *   "unit": "eur_m2",
 *   "description": "Prix du chêne au m²"
 * }
 */
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    // Validation des champs requis
    $required_fields = ['category', 'item_type', 'param_name', 'param_value', 'unit'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            exit;
        }
    }

    // Validation du prix
    if (!is_numeric($data['param_value'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'param_value must be a number']);
        exit;
    }

    try {
        $sql = 'INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description, is_active)
                VALUES (:category, :item_type, :param_name, :param_value, :unit, :description, :is_active)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':category' => trim($data['category']),
            ':item_type' => trim($data['item_type']),
            ':param_name' => trim($data['param_name']),
            ':param_value' => floatval($data['param_value']),
            ':unit' => trim($data['unit']),
            ':description' => isset($data['description']) ? trim($data['description']) : null,
            ':is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1
        ]);

        $new_id = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Pricing parameter created successfully',
            'id' => $new_id
        ]);
    } catch (PDOException $e) {
        // Gérer les doublons (UNIQUE constraint)
        if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'This parameter already exists']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    exit;
}

/**
 * PUT - Mettre à jour un paramètre existant
 *
 * Body JSON:
 * {
 *   "id": 1,
 *   "param_value": 250,
 *   "description": "Nouveau prix",
 *   "is_active": 1
 * }
 */
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing id']);
        exit;
    }

    try {
        // Vérifier que le paramètre existe
        $stmt = $db->prepare('SELECT id FROM pricing_config WHERE id = :id');
        $stmt->execute([':id' => intval($data['id'])]);

        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Parameter not found']);
            exit;
        }

        // Construire la requête de mise à jour dynamiquement
        $updates = [];
        $params = [':id' => intval($data['id'])];

        if (isset($data['param_value'])) {
            if (!is_numeric($data['param_value'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'param_value must be a number']);
                exit;
            }
            $updates[] = 'param_value = :param_value';
            $params[':param_value'] = floatval($data['param_value']);
        }

        if (isset($data['description'])) {
            $updates[] = 'description = :description';
            $params[':description'] = trim($data['description']);
        }

        if (isset($data['unit'])) {
            $updates[] = 'unit = :unit';
            $params[':unit'] = trim($data['unit']);
        }

        if (isset($data['is_active'])) {
            $updates[] = 'is_active = :is_active';
            $params[':is_active'] = intval($data['is_active']);
        }

        if (count($updates) === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit;
        }

        $sql = 'UPDATE pricing_config SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Parameter updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * DELETE - Désactiver un paramètre (soft delete)
 *
 * Query param:
 * - id: ID du paramètre à désactiver
 */
if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing id']);
        exit;
    }

    try {
        $stmt = $db->prepare('UPDATE pricing_config SET is_active = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Parameter not found']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Parameter deactivated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Méthode non supportée
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
