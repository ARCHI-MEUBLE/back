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
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../models/Admin.php';

$session = Session::getInstance();
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
        // Régénérer l'ID de session pour prévenir la fixation de session
        $session->regenerate();

        // SÉCURITÉ: Effacer toutes les données customer si présentes
        $session->remove('customer_id');
        $session->remove('customer_email');
        $session->remove('customer_name');

        // Créer une session admin
        $session->set('admin_email', $adminData['email']);
        $session->set('admin_id', $adminData['id']);
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
    // Détruire la session
    $session->destroy();

    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

/**
 * GET /api/admin-auth/session
 */
if ($method === 'GET' && $action === 'session') {
    // SÉCURITÉ: Vérifier qu'il n'y a pas de session customer active
    if ($session->has('customer_id')) {
        // Session customer détectée, pas de session admin
        http_response_code(200);
        echo json_encode(['admin' => null]);
        exit;
    }

    if ($session->has('admin_email') && !empty($session->get('admin_email'))) {
        // Récupérer les infos complètes de l'admin
        $adminData = $admin->getByEmail($session->get('admin_email'));

        http_response_code(200);
        echo json_encode([
            'admin' => [
                'email' => $adminData['email'] ?? $session->get('admin_email'),
                'username' => $adminData['username'] ?? 'Admin',
                'id' => $adminData['id'] ?? $session->get('admin_id') ?? null
            ]
        ]);
    } else {
        // Retourner 200 avec admin null au lieu de 401 pour éviter les erreurs console
        http_response_code(200);
        echo json_encode(['admin' => null]);
    }
    exit;
}

// Route non trouvée
http_response_code(404);
echo json_encode(['error' => 'Endpoint non trouvé']);
