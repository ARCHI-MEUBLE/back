<?php
/**
 * API Catalogue Public - Consultation du catalogue
 * Permet aux utilisateurs de consulter les articles disponibles
 */
require_once __DIR__ . '/../core/Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $pdo = Database::getInstance()->getPDO();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
        exit;
    }

    switch ($action) {
        case 'list':
            // Récupérer les articles disponibles avec filtres optionnels
            $category = $_GET['category'] ?? null;
            $search = $_GET['search'] ?? null;
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);

            $sql = "SELECT id, name, category, description, material, dimensions,
                           unit_price, unit, min_order_quantity, image_url, weight, tags, variation_label
                    FROM catalogue_items
                    WHERE is_available = TRUE";
            $params = [];

            if ($category) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }

            if ($search) {
                $sql .= " AND (name LIKE ? OR description LIKE ? OR tags LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Charger les variations pour tous les items retournés
            if (!empty($items)) {
                $ids = array_column($items, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $varStmt = $pdo->prepare("SELECT id, catalogue_item_id, color_name, image_url, is_default FROM catalogue_item_variations WHERE catalogue_item_id IN ($placeholders) ORDER BY is_default DESC, id ASC");
                $varStmt->execute($ids);
                $variations = $varStmt->fetchAll(PDO::FETCH_ASSOC);

                $byItem = [];
                foreach ($variations as $v) {
                    $byItem[$v['catalogue_item_id']][] = [
                        'id' => (int)$v['id'],
                        'color_name' => $v['color_name'],
                        'image_url' => $v['image_url'],
                        'is_default' => (int)$v['is_default']
                    ];
                }

                foreach ($items as &$it) {
                    $it['variations'] = $byItem[$it['id']] ?? [];
                }
            }

            // Compter le total pour la pagination
            $countSql = "SELECT COUNT(*) FROM catalogue_items WHERE is_available = TRUE";
            $countParams = [];
            if ($category) {
                $countSql .= " AND category = ?";
                $countParams[] = $category;
            }
            if ($search) {
                $countSql .= " AND (name LIKE ? OR description LIKE ? OR tags LIKE ?)";
                $countParams[] = "%$search%";
                $countParams[] = "%$search%";
                $countParams[] = "%$search%";
            }
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'data' => $items,
                'pagination' => [
                    'total' => (int)$total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);
            break;

        case 'item':
            // Récupérer un article spécifique
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requis']);
                return;
            }

            $stmt = $pdo->prepare("SELECT id, name, category, description, material, dimensions,
                                          unit_price, unit, min_order_quantity, image_url, weight, tags, variation_label
                                   FROM catalogue_items
                                   WHERE id = ? AND is_available = TRUE");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Article non trouvé']);
                return;
            }

            // Ajouter les variations
            $varStmt = $pdo->prepare("SELECT id, color_name, image_url, is_default FROM catalogue_item_variations WHERE catalogue_item_id = ? ORDER BY is_default DESC, id ASC");
            $varStmt->execute([$id]);
            $item['variations'] = [];
            foreach ($varStmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $item['variations'][] = [
                    'id' => (int)$v['id'],
                    'color_name' => $v['color_name'],
                    'image_url' => $v['image_url'],
                    'is_default' => (int)$v['is_default']
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $item
            ]);
            break;

        case 'categories':
            // Récupérer les catégories disponibles
            $stmt = $pdo->query("SELECT DISTINCT category FROM catalogue_items WHERE is_available = TRUE ORDER BY category");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode([
                'success' => true,
                'data' => $categories
            ]);
            break;

        case 'materials':
            // Récupérer les matériaux disponibles
            $materials = ['Aggloméré', 'MDF + Revêtement (mélaminé)', 'Plaqué bois'];
            echo json_encode([
                'success' => true,
                'data' => $materials
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non supportée']);
    }
} catch (PDOException $e) {
    error_log('PDO Error API catalogue public: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Erreur API catalogue public: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal Server Error: ' . $e->getMessage()]);
}
