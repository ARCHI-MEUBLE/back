<?php
/**
 * ArchiMeuble - Endpoint d'authentification administrateur
 * POST /api/admin-auth/login - Connexion admin
 * POST /api/admin-auth/logout - Déconnexion admin
 * GET /api/admin-auth/session - Vérifier la session admin
 *
 * Date : 2025-11-06
 */

require_once __DIR__ . '/../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Admin.php';

$admin = new Admin();
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Extraire l'action de l'URI
$pathParts = explode('/', trim($requestUri, '/'));
$action = end($pathParts);

// Enlever l'extension .php si présente et les query params
$action = preg_replace('/\.php(\?.*)?$/', '', $action);

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
        // Créer une session admin en utilisant $_SESSION natif
        $_SESSION['admin_email'] = $adminData['email'];
        $_SESSION['admin_id'] = $adminData['id'];

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
    // Détruire la session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

/**
 * GET /api/admin-auth/session
 */
if ($method === 'GET' && $action === 'session') {
    if (isset($_SESSION['admin_email']) && !empty($_SESSION['admin_email'])) {
        http_response_code(200);
        echo json_encode([
            'admin' => [
                'email' => $_SESSION['admin_email']
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
