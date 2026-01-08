<?php
/**
 * API Admin: Créer un nouveau compte administrateur
 * POST /api/admin/register
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';

// Disable HTML error output
ini_set('display_errors', 0);
error_reporting(E_ALL);

$session = Session::getInstance();

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;
    $confirmPassword = $input['confirmPassword'] ?? null;

    // Validation
    if (!$email || !$password || !$confirmPassword) {
        http_response_code(400);
        echo json_encode(['error' => 'Tous les champs sont requis']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email invalide']);
        exit;
    }

    if ($password !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['error' => 'Les mots de passe ne correspondent pas']);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Le mot de passe doit contenir au moins 6 caractères']);
        exit;
    }

    require_once __DIR__ . '/../../core/Database.php';
    $db = Database::getInstance();

    // Vérifier si l'email existe déjà
    $existing = $db->queryOne("SELECT id FROM admins WHERE email = ?", [$email]);
    if ($existing) {
        http_response_code(409);
        echo json_encode(['error' => 'Un compte avec cet email existe déjà']);
        exit;
    }

    // Créer le hash du mot de passe
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Extraire le username de l'email
    $username = explode('@', $email)[0];

    // Insérer le nouvel admin
    $query = "INSERT INTO admins (username, email, password, created_at) VALUES (?, ?, ?, datetime('now'))";
    $success = $db->execute($query, [$username, $email, $passwordHash]);

    if (!$success) {
        throw new Exception("Failed to insert admin");
    }

    $adminId = $db->lastInsertId();

    // Régénérer l'ID de session pour prévenir la fixation de session
    $session->regenerate();

    // Créer une session
    $session->set('admin_id', $adminId);
    $session->set('admin_email', $email);
    $session->set('admin_username', $username);
    $session->set('is_admin', true);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Compte créé avec succès',
        'admin' => [
            'id' => $adminId,
            'email' => $email,
            'username' => $username
        ]
    ]);

} catch (Exception $e) {
    error_log("Register error: " . $e->getMessage());
    error_log("Register trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur lors de la création du compte',
        'details' => $e->getMessage()
    ]);
}
