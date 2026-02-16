<?php
/**
 * API: Réinitialisation du mot de passe
 * POST /api/customers/reset-password
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../models/Customer.php';

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
    $token = $data['token'] ?? '';
    $newPassword = $data['password'] ?? '';

    if (empty($token) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token et nouveau mot de passe requis']);
        exit;
    }

    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Le mot de passe doit contenir au moins 8 caractères']);
        exit;
    }

    $db = Database::getInstance();
    
    // Vérifier le token
    $reset = $db->queryOne(
        "SELECT * FROM password_resets WHERE token = ? AND expires_at > CURRENT_TIMESTAMP",
        [$token]
    );

    if (!$reset) {
        http_response_code(400);
        echo json_encode(['error' => 'Lien de réinitialisation invalide ou expiré']);
        exit;
    }

    $email = $reset['email'];
    $customerModel = new Customer();
    $customer = $customerModel->getByEmail($email);

    if (!$customer) {
        http_response_code(404);
        echo json_encode(['error' => 'Client introuvable']);
        exit;
    }

    // Mettre à jour le mot de passe
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $db->execute(
        "UPDATE customers SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        [$passwordHash, $customer['id']]
    );

    // Supprimer le token utilisé
    $db->execute("DELETE FROM password_resets WHERE email = ?", [$email]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Votre mot de passe a été mis à jour avec succès.'
    ]);

} catch (Exception $e) {
    error_log("[RESET PASSWORD] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
