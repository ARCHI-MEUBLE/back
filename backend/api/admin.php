<?php
/**
 * ArchiMeuble - Endpoint d'authentification administrateur
 * POST /api/admin/login - Connexion admin
 * POST /api/admin/logout - Déconnexion admin
 * GET /api/admin/session - Vérifier la session admin
 *
 * Date : 2025-10-22
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Cors.php';
require_once __DIR__ . '/../models/Admin.php';

// Activer CORS
Cors::enable();

$session = Session::getInstance();
$admin = new Admin();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Extraire l'action de l'URI
$pathParts = explode('/', trim($requestUri, '/'));
$action = end($pathParts);

/**
 * POST /api/admin/login
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
 * POST /api/admin/register
 */
if ($method === 'POST' && $action === 'register') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email et mot de passe requis']);
        exit;
    }

    // Vérifier si l'email existe déjà
    if ($admin->emailExists($input['email'])) {
        http_response_code(409);
        echo json_encode(['error' => 'Un administrateur avec cet email existe déjà']);
        exit;
    }

    // Générer un username basé sur l'email (partie avant le @)
    $username = explode('@', $input['email'])[0];

    // Créer le nouvel admin
    $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT);
    $success = $admin->create($input['email'], $passwordHash, $username);

    if ($success) {
        // Créer une session pour le nouvel admin
        $session->set('admin_email', $input['email']);
        $session->set('is_admin', true);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'admin' => [
                'email' => $input['email']
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la création de l\'administrateur']);
    }
    exit;
}

/**
 * POST /api/admin/logout
 */
if ($method === 'POST' && $action === 'logout') {
    $session->destroy();
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

/**
 * GET /api/admin/session
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
