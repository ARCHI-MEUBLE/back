<?php
/**
 * API: Renvoyer le code de vérification
 * POST /api/customers/resend-code
 *
 * Génère et envoie un nouveau code de vérification
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../models/Customer.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../services/EmailService.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $email = $data['email'] ?? '';

    // Validation
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email requis']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email invalide']);
        exit;
    }

    $db = Database::getInstance();
    $customer = new Customer();

    // Vérifier que le client existe
    $customerData = $customer->getByEmail($email);
    if (!$customerData) {
        // Pour des raisons de sécurité, ne pas révéler si l'email existe
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Si un compte existe avec cet email, un nouveau code a été envoyé.'
        ]);
        exit;
    }

    // Vérifier si déjà vérifié
    if (isset($customerData['email_verified']) && $customerData['email_verified']) {
        http_response_code(400);
        echo json_encode(['error' => 'Ce compte est déjà vérifié. Vous pouvez vous connecter.']);
        exit;
    }

    // Vérifier le rate limiting (max 3 codes par heure)
    $recentCodes = $db->queryOne(
        "SELECT COUNT(*) as count FROM email_verifications WHERE email = ? AND created_at > NOW() - INTERVAL '1 hour'",
        [$email]
    );

    if ($recentCodes && $recentCodes['count'] >= 3) {
        http_response_code(429);
        echo json_encode(['error' => 'Trop de demandes. Veuillez attendre avant de demander un nouveau code.']);
        exit;
    }

    // Générer un nouveau code à 6 chiffres
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Supprimer les anciens codes non utilisés
    $db->execute("DELETE FROM email_verifications WHERE email = ? AND used = FALSE", [$email]);

    // Insérer le nouveau code
    $db->execute(
        "INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)",
        [$email, $code, $expiresAt]
    );

    // Envoyer l'email de vérification
    $emailService = new EmailService();
    $emailSent = $emailService->sendVerificationEmail($email, $customerData['first_name'], $code);

    if (!$emailSent) {
        error_log("[RESEND-CODE] Failed to send verification email to: " . $email);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Un nouveau code de vérification a été envoyé à votre adresse email.'
    ]);

} catch (Exception $e) {
    error_log("[RESEND-CODE] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
