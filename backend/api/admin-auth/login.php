<?php
/**
 * API Admin Auth: Connexion admin
 * POST /backend/api/admin-auth/login
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

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? (string)$data['password'] : '';

    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Email et mot de passe requis']);
        exit;
    }

    // Simple authentification statique (à remplacer par une table Admin si disponible)
    $DEFAULT_ADMIN_EMAIL = 'admin@archimeuble.local';
    $DEFAULT_ADMIN_PASSWORD = 'admin12345';

    if ($email !== $DEFAULT_ADMIN_EMAIL || $password !== $DEFAULT_ADMIN_PASSWORD) {
        http_response_code(401);
        echo json_encode(['error' => 'Identifiants incorrects']);
        exit;
    }

    // Définir la session admin côté PHP
    $_SESSION['admin_email'] = $email;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Authentification réussie',
        'admin' => [
            'email' => $email
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
