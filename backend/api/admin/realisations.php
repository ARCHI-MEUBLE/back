<?php
/**
 * ArchiMeuble - Endpoint des réalisations (Admin)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../models/Realisation.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$realisation = new Realisation();
$method = $_SERVER['REQUEST_METHOD'];

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

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $data = $realisation->getById($_GET['id']);
        } else {
            $data = $realisation->getAll();
        }
        echo json_encode(['success' => true, 'realisations' => $data]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['titre'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Le titre est obligatoire']);
            exit;
        }

        $id = $realisation->create($input);
        if ($id) {
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Réalisation créée']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
        }
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID manquant']);
            exit;
        }

        $id = $input['id'];
        unset($input['id']);
        
        if ($realisation->update($id, $input)) {
            echo json_encode(['success' => true, 'message' => 'Réalisation mise à jour']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
        }
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID manquant']);
            exit;
        }

        if ($realisation->delete($id)) {
            echo json_encode(['success' => true, 'message' => 'Réalisation supprimée']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        break;
}
?>
