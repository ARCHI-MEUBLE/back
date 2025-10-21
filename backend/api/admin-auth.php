<?php
/**
 * ArchiMeuble - Endpoint d'authentification administrateur
 * POST /api/admin-auth/login - Connexion admin
 * POST /api/admin-auth/logout - Déconnexion admin
 * GET /api/admin-auth/session - Vérifier la session admin
 *
 * Date : 2025-10-21
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../models/Admin.php';

// Headers CORS
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$session = Session::getInstance();
$admin = new Admin();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Extraire l'action de l'URI
$pathParts = explode('/', trim($requestUri, '/'));
$action = end($pathParts);

/**
 * POST /api/admin-auth/login
 */
if ($method === 'POST' && $action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email et mot de passe requis']);
        exit;
    }

    $adminData = $admin->verifyCredentials($input['email'], $input['password']);

    if ($adminData) {
        // Créer une session admin
        $session->set('admin_email', $adminData['email']);
        $session->set('is_admin', true);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'admin' => [
                'email' => $adminData['email']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Identifiants invalides']);
    }
    exit;
}

/**
 * POST /api/admin-auth/logout
 */
if ($method === 'POST' && $action === 'logout') {
    $session->destroy();
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

/**
 * GET /api/admin-auth/session
 */
if ($method === 'GET' && $action === 'session') {
    if ($session->has('is_admin') && $session->get('is_admin') === true) {
        http_response_code(200);
        echo json_encode([
            'admin' => [
                'email' => $session->get('admin_email')
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Non authentifié']);
    }
    exit;
}

// Route non trouvée
http_response_code(404);
echo json_encode(['error' => 'Endpoint non trouvé']);
