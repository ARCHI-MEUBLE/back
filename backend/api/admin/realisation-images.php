<?php
/**
 * API pour gérer les images des réalisations
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification
function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../../core/Session.php';
        Session::getInstance();
    }
    return isset($_SESSION['admin_email']) && !empty($_SESSION['admin_email']);
}

if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getPDO();

// GET - Récupérer les images d'une réalisation
if ($method === 'GET') {
    $realisation_id = isset($_GET['realisation_id']) ? intval($_GET['realisation_id']) : 0;
    
    if ($realisation_id > 0) {
        $stmt = $db->prepare('SELECT * FROM realisation_images WHERE realisation_id = ? ORDER BY ordre ASC');
        $stmt->execute([$realisation_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'images' => $images]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de réalisation requis']);
    }
}

// POST - Ajouter une nouvelle image
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $realisation_id = isset($data['realisation_id']) ? intval($data['realisation_id']) : 0;
    $image_url = isset($data['image_url']) ? trim($data['image_url']) : '';
    $legende = isset($data['legende']) ? trim($data['legende']) : '';
    $ordre = isset($data['ordre']) ? intval($data['ordre']) : 0;
    
    if ($realisation_id > 0 && !empty($image_url)) {
        // Si l'ordre n'est pas spécifié, prendre le suivant disponible
        if ($ordre === 0) {
            $stmt = $db->prepare('SELECT COALESCE(MAX(ordre), 0) + 1 as next_ordre FROM realisation_images WHERE realisation_id = ?');
            $stmt->execute([$realisation_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $ordre = $result['next_ordre'];
        }
        
        $stmt = $db->prepare('INSERT INTO realisation_images (realisation_id, image_url, legende, ordre) VALUES (?, ?, ?, ?)');
        $success = $stmt->execute([$realisation_id, $image_url, $legende, $ordre]);
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'id' => $db->lastInsertId(),
                'message' => 'Image ajoutée avec succès'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout de l\'image']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
    }
}

// PUT - Mettre à jour une image
elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($data['id']) ? intval($data['id']) : 0;
    $image_url = isset($data['image_url']) ? trim($data['image_url']) : '';
    $legende = isset($data['legende']) ? trim($data['legende']) : '';
    $ordre = isset($data['ordre']) ? intval($data['ordre']) : 0;
    
    if ($id > 0) {
        $updates = [];
        $params = [];
        
        if (!empty($image_url)) {
            $updates[] = 'image_url = ?';
            $params[] = $image_url;
        }
        if (isset($data['legende'])) {
            $updates[] = 'legende = ?';
            $params[] = $legende;
        }
        if ($ordre > 0) {
            $updates[] = 'ordre = ?';
            $params[] = $ordre;
        }
        
        if (!empty($updates)) {
            $params[] = $id;
            $sql = 'UPDATE realisation_images SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $stmt = $db->prepare($sql);
            $success = $stmt->execute($params);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Image mise à jour']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Aucune donnée à mettre à jour']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
    }
}

// DELETE - Supprimer une image
elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM realisation_images WHERE id = ?');
        $success = $stmt->execute([$id]);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Image supprimée']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
    }
}

else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
