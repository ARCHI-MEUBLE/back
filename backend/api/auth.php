<?php
/**
 * ArchiMeuble - Endpoint d'authentification utilisateur
 * POST /api/auth/login - Connexion
 * POST /api/auth/register - Inscription
 * GET /api/auth/session - Vérifier la session
 * DELETE /api/auth/logout - Déconnexion
 * PUT /api/auth/password - Changer le mot de passe
 *
 * Date : 2025-10-21
 */

// Gestionnaire d'erreurs global pour retourner du JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur serveur',
        'message' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Erreur fatale',
            'message' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Cors.php';
require_once __DIR__ . '/../models/User.php';

// Activer CORS
Cors::enable();

$session = Session::getInstance();
$user = new User();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Extraire l'action de l'URI (login, register, session, logout, password)
$pathParts = explode('/', trim($requestUri, '/'));
$action = end($pathParts);

// Enlever l'extension .php si présente et les query params
$action = preg_replace('/\.php(\?.*)?$/', '', $action);

/**
 * POST /api/auth/login
 */
if ($method === 'POST' && $action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email et mot de passe requis']);
        exit;
    }

    $userData = $user->verifyCredentials($input['email'], $input['password']);

    if ($userData) {
        // Créer une session
        $session->set('user_id', $userData['id']);
        $session->set('user_email', $userData['email']);
        $session->set('user_name', $userData['name']);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userData['id'],
                'email' => $userData['email'],
                'name' => $userData['name']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Identifiants invalides']);
    }
    exit;
}

/**
 * POST /api/auth/register
 */
if ($method === 'POST' && $action === 'register') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email et mot de passe requis']);
        exit;
    }

    // Vérifier si l'email existe déjà
    if ($user->emailExists($input['email'])) {
        http_response_code(409);
        echo json_encode(['error' => 'Cet email est déjà utilisé']);
        exit;
    }

    $name = $input['name'] ?? null;

    // Générer un ID unique
    $userId = uniqid('user_', true);

    // Hasher le mot de passe
    $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT);

    // Créer l'utilisateur
    $success = $user->create($userId, $input['email'], $passwordHash, $name);

    if ($success) {
        // Créer une session automatiquement après l'inscription
        $session->set('user_id', $userId);
        $session->set('user_email', $input['email']);
        $session->set('user_name', $name);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'email' => $input['email'],
                'name' => $name
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la création du compte']);
    }
    exit;
}

/**
 * GET /api/auth/session
 */
if ($method === 'GET' && $action === 'session') {
    if ($session->has('user_id')) {
        http_response_code(200);
        echo json_encode([
            'user' => [
                'id' => $session->get('user_id'),
                'email' => $session->get('user_email'),
                'name' => $session->get('user_name')
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Non authentifié']);
    }
    exit;
}

/**
 * DELETE /api/auth/logout
 */
if ($method === 'DELETE' && $action === 'logout') {
    $session->destroy();
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

/**
 * PUT /api/auth/password
 */
if ($method === 'PUT' && $action === 'password') {
    if (!$session->has('user_id')) {
        http_response_code(401);
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['currentPassword']) || !isset($input['newPassword'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Mots de passe requis']);
        exit;
    }

    $userId = $session->get('user_id');
    $email = $session->get('user_email');

    // Vérifier l'ancien mot de passe
    if (!$user->verifyCredentials($email, $input['currentPassword'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Mot de passe actuel incorrect']);
        exit;
    }

    // Mettre à jour le mot de passe
    if ($user->update($userId, ['password' => $input['newPassword']])) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la mise à jour du mot de passe']);
    }
    exit;
}

// Route non trouvée
http_response_code(404);
echo json_encode(['error' => 'Endpoint non trouvé']);
