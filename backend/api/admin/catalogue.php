<?php
/**
 * API Admin Catalogue - Gestion des articles du catalogue
 * Permet la gestion complète des articles (CRUD) et des catégories
 */
require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification admin
require_once __DIR__ . '/../../core/Session.php';
$session = Session::getInstance();
if (!isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentification requise']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $pdo = Database::getInstance()->getPDO();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';

    switch ($method) {
        case 'GET':
            handleGet($action, $pdo);
            break;
        case 'POST':
            handlePost($action, $pdo);
            break;
        case 'PUT':
            handlePut($action, $pdo);
            break;
        case 'DELETE':
            handleDelete($action, $pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    }
} catch (PDOException $e) {
        error_log('PDO Error API catalogue admin: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log('Erreur API catalogue admin: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal Server Error: ' . $e->getMessage()]);
    }

function handleGet($action, $pdo) {
    switch ($action) {
        case 'list':
            // Récupérer tous les articles avec filtres optionnels
            $category = $_GET['category'] ?? null;
            $search = $_GET['search'] ?? null;
            $available = $_GET['available'] ?? null;

            $sql = "SELECT * FROM catalogue_items WHERE 1=1";
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

            if ($available !== null) {
                $sql .= " AND is_available = ?";
                $params[] = (int)$available;
            }

            $sql .= " ORDER BY created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $items
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

            $stmt = $pdo->prepare("SELECT * FROM catalogue_items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Article non trouvé']);
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => $item
            ]);
            break;

        case 'categories':
            // Récupérer les catégories disponibles
            $stmt = $pdo->query("SELECT DISTINCT category FROM catalogue_items WHERE category IS NOT NULL AND category != '' ORDER BY category");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Ajouter les catégories par défaut si elles n'existent pas
            $defaultCategories = ['Portes', 'Planches à découper'];
            $allCategories = array_unique(array_merge($defaultCategories, $categories));
            
            // Réindexer le tableau pour garantir un tableau JSON (pas un objet)
            $allCategories = array_values($allCategories);

            echo json_encode([
                'success' => true,
                'data' => $allCategories
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
}

function handlePost($action, $pdo) {
    switch ($action) {
        case 'create':
            // Créer un nouvel article
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Données JSON requises']);
                return;
            }

            // Validation des champs requis
            $required = ['name', 'category', 'unit_price'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => "Champ requis manquant: $field"]);
                    return;
                }
            }

            $sql = "INSERT INTO catalogue_items (
                name, category, description, material, dimensions,
                unit_price, unit, stock_quantity, min_order_quantity,
                is_available, image_url, weight, tags, variation_label
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['category'],
                $data['description'] ?? null,
                $data['material'] ?? null,
                $data['dimensions'] ?? null,
                (float)$data['unit_price'],
                $data['unit'] ?? 'pièce',
                (int)($data['stock_quantity'] ?? 0),
                (int)($data['min_order_quantity'] ?? 1),
                (isset($data['is_available']) && $data['is_available'] !== '') ? filter_var($data['is_available'], FILTER_VALIDATE_BOOLEAN) : true,
                $data['image_url'] ?? null,
                isset($data['weight']) ? (float)$data['weight'] : null,
                $data['tags'] ?? null,
                $data['variation_label'] ?? 'Couleur / Finition'
            ]);

            $newId = $pdo->lastInsertId();
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Article ajouté avec succès', 'id' => $newId]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non supportée']);
    }
}

function handlePut($action, $pdo) {
    switch ($action) {
        case 'update':
            // Mettre à jour un article
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requis']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Données JSON requises']);
                return;
            }

            // Vérifier que l'article existe
            $stmt = $pdo->prepare("SELECT id FROM catalogue_items WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Article non trouvé']);
                return;
            }

            $sql = "UPDATE catalogue_items SET
                name = ?, category = ?, description = ?, material = ?, dimensions = ?,
                unit_price = ?, unit = ?, stock_quantity = ?, min_order_quantity = ?,
                is_available = ?, image_url = ?, weight = ?, tags = ?, variation_label = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['name'] ?? '',
                $data['category'] ?? '',
                $data['description'] ?? null,
                $data['material'] ?? null,
                $data['dimensions'] ?? null,
                isset($data['unit_price']) ? (float)$data['unit_price'] : 0,
                $data['unit'] ?? 'pièce',
                isset($data['stock_quantity']) ? (int)$data['stock_quantity'] : 0,
                isset($data['min_order_quantity']) ? (int)$data['min_order_quantity'] : 1,
                (isset($data['is_available']) && $data['is_available'] !== '') ? filter_var($data['is_available'], FILTER_VALIDATE_BOOLEAN) : true,
                $data['image_url'] ?? null,
                isset($data['weight']) ? (float)$data['weight'] : null,
                $data['tags'] ?? null,
                $data['variation_label'] ?? 'Couleur / Finition',
                $id
            ]);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Article mis à jour avec succès']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non supportée']);
    }
}

function handleDelete($action, $pdo) {
    switch ($action) {
        case 'delete':
            // Supprimer un article
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requis']);
                return;
            }

            $stmt = $pdo->prepare("DELETE FROM catalogue_items WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Article non trouvé']);
                return;
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Article supprimé avec succès']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non supportée']);
    }
}
