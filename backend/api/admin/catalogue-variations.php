<?php
/**
 * API Admin Variations Catalogue - Gestion des variations de couleur/image
 * Permet de gérer les différentes images d'un article (couleurs, etc)
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
        case 'DELETE':
            handleDelete($action, $pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    }
} catch (Exception $e) {
    error_log('Erreur API variations catalogue: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur interne du serveur']);
}

function handleGet($action, $pdo) {
    switch ($action) {
        case 'list':
            // Récupérer les variations d'un article
            $itemId = $_GET['item_id'] ?? null;
            if (!$itemId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'item_id requis']);
                return;
            }

            $stmt = $pdo->prepare("
                 SELECT id, color_name, image_url, is_default 
                FROM catalogue_item_variations 
                WHERE catalogue_item_id = ? 
                ORDER BY is_default DESC, id ASC
            ");
            $stmt->execute([$itemId]);
            $variations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $variations
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non supportée']);
    }
}

function handlePost($action, $pdo) {
    switch ($action) {
        case 'add':
            // Ajouter une variation
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['catalogue_item_id']) || !isset($data['color_name']) || !isset($data['image_url'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
                    return;
            }

            // Vérifier si la variation existe déjà
            $stmt = $pdo->prepare("SELECT id FROM catalogue_item_variations WHERE catalogue_item_id = ? AND color_name = ?");
            $stmt->execute([$data['catalogue_item_id'], $data['color_name']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cette variation existe déjà pour cet article']);
                return;
            }

            $sql = "INSERT INTO catalogue_item_variations (catalogue_item_id, color_name, image_url, is_default) 
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['catalogue_item_id'],
                $data['color_name'],
                $data['image_url'],
                (isset($data['is_default']) && $data['is_default'] !== '') ? filter_var($data['is_default'], FILTER_VALIDATE_BOOLEAN) : false
            ]);

            $newId = $pdo->lastInsertId();
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Variation ajoutée avec succès', 'id' => $newId]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non supportée']);
    }
}

function handleDelete($action, $pdo) {
    switch ($action) {
        case 'delete':
            // Supprimer une variation
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requis']);
                return;
            }

            $stmt = $pdo->prepare("DELETE FROM catalogue_item_variations WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Variation non trouvée']);
                return;
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Variation supprimée avec succès']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non supportée']);
    }
}
