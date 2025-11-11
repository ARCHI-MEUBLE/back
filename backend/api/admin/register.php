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

try {
    $input = json_decode(file_get_contents('php://input'), true);

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
    $db->execute($query, [$username, $email, $passwordHash]);

    $adminId = $db->lastInsertId();

    // Créer une session
    session_start();
    $_SESSION['admin_id'] = $adminId;
    $_SESSION['admin_email'] = $email;
    $_SESSION['admin_username'] = $username;

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
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la création du compte']);
}
