<?php
/**
 * API: Demande de réinitialisation de mot de passe
 * POST /api/customers/forgot-password
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../models/Customer.php';
require_once __DIR__ . '/../../services/EmailService.php';

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
    $email = $data['email'] ?? '';

    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email requis']);
        exit;
    }

    $customerModel = new Customer();
    $customer = $customerModel->getByEmail($email);

    if ($customer) {
        $db = Database::getInstance();
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Supprimer les anciens tokens pour cet email
        $db->execute("DELETE FROM password_resets WHERE email = ?", [$email]);

        // Insérer le nouveau token
        $db->execute(
            "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)",
            [$email, $token, $expiresAt]
        );

        // Envoyer l'email
        $emailService = new EmailService();
        $frontendUrl = getenv('FRONTEND_URL') ?: 'http://localhost:3000';
        $resetUrl = "{$frontendUrl}/auth/reset-password?token={$token}";
        $name = $customer['first_name'] ?: 'Client';

        $emailService->sendPasswordResetEmail($email, $name, $resetUrl);
    }

    // Toujours retourner un message de succès pour des raisons de sécurité (ne pas révéler si l'email existe)
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Si cet email est associé à un compte, un lien de réinitialisation a été envoyé.'
    ]);

} catch (Exception $e) {
    error_log("[FORGOT PASSWORD] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
