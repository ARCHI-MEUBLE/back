<?php
/**
 * API: Inscription client
 * POST /api/customers/register
 *
 * Crée un compte non vérifié et envoie un code de vérification par email
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

    // Validation
    $required = ['email', 'password', 'first_name', 'last_name', 'phone', 'address', 'city', 'postal_code', 'country'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Le champ $field est requis"]);
            exit;
        }
    }

    // Valider l'email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email invalide']);
        exit;
    }

    // Valider le mot de passe (au moins 8 caractères)
    if (strlen($data['password']) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Le mot de passe doit contenir au moins 8 caractères']);
        exit;
    }

    // Valider le format du téléphone (France ou international basique)
    // Format attendu: +33612345678 ou 0612345678
    if (!preg_match('/^(\+33|0)[1-9](\d{2}){4}$/', str_replace(' ', '', $data['phone']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Format de téléphone invalide (ex: 0612345678 ou +33612345678)']);
        exit;
    }

    // Valider le code postal (5 chiffres pour la France)
    if ($data['country'] === 'France' && !preg_match('/^\d{5}$/', $data['postal_code'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Le code postal doit contenir exactement 5 chiffres']);
        exit;
    }

    $customer = new Customer();
    $db = Database::getInstance();

    // Vérifier si l'email existe déjà
    $existingCustomer = $customer->getByEmail($data['email']);
    if ($existingCustomer) {
        // Si le compte existe mais n'est pas vérifié, permettre de renvoyer le code
        if (isset($existingCustomer['email_verified']) && $existingCustomer['email_verified'] == 0) {
            // Générer un nouveau code et renvoyer
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Supprimer les anciens codes
            $db->execute("DELETE FROM email_verifications WHERE email = ?", [$data['email']]);

            // Insérer le nouveau code
            $db->execute(
                "INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)",
                [$data['email'], $code, $expiresAt]
            );

            // Envoyer l'email
            $emailService = new EmailService();
            $emailService->sendVerificationEmail($data['email'], $existingCustomer['first_name'], $code);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Un nouveau code de vérification a été envoyé',
                'requiresVerification' => true,
                'email' => $data['email']
            ]);
            exit;
        }

        http_response_code(409);
        echo json_encode(['error' => 'Un compte avec cet email existe déjà']);
        exit;
    }

    // Créer le client (non vérifié par défaut)
    $customerId = $customer->create(
        $data['email'],
        $data['password'],
        $data['first_name'],
        $data['last_name'],
        $data['phone'] ?? null,
        $data['address'] ?? null,
        $data['city'] ?? null,
        $data['postal_code'] ?? null,
        $data['country'] ?? 'France'
    );

    // Générer un code à 6 chiffres
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Supprimer les anciens codes pour cet email (au cas où)
    $db->execute("DELETE FROM email_verifications WHERE email = ?", [$data['email']]);

    // Insérer le nouveau code
    $db->execute(
        "INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)",
        [$data['email'], $code, $expiresAt]
    );

    // Envoyer l'email de vérification
    $emailService = new EmailService();
    $emailSent = $emailService->sendVerificationEmail($data['email'], $data['first_name'], $code);

    if (!$emailSent) {
        error_log("[REGISTER] Failed to send verification email to: " . $data['email']);
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Compte créé. Veuillez vérifier votre email pour activer votre compte.',
        'requiresVerification' => true,
        'email' => $data['email']
    ]);

} catch (Exception $e) {
    error_log("[REGISTER] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
