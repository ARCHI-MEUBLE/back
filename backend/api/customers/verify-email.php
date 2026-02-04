<?php
/**
 * API: Vérification email client
 * POST /api/customers/verify-email
 *
 * Vérifie le code à 6 chiffres et active le compte
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../models/Customer.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $email = $data['email'] ?? '';
    $code = $data['code'] ?? '';

    // Validation
    if (empty($email) || empty($code)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email et code requis']);
        exit;
    }

    // Valider le format du code (6 chiffres)
    if (!preg_match('/^\d{6}$/', $code)) {
        http_response_code(400);
        echo json_encode(['error' => 'Le code doit contenir 6 chiffres']);
        exit;
    }

    $db = Database::getInstance();
    $customer = new Customer();

    // Vérifier que le client existe
    $customerData = $customer->getByEmail($email);
    if (!$customerData) {
        http_response_code(404);
        echo json_encode(['error' => 'Compte non trouvé']);
        exit;
    }

    // Vérifier si déjà vérifié
    if (isset($customerData['email_verified']) && $customerData['email_verified']) {
        http_response_code(400);
        echo json_encode(['error' => 'Ce compte est déjà vérifié']);
        exit;
    }

    // Récupérer le code de vérification
    $verification = $db->queryOne(
        "SELECT * FROM email_verifications WHERE email = ? AND code = ? AND used = FALSE ORDER BY created_at DESC LIMIT 1",
        [$email, $code]
    );

    if (!$verification) {
        http_response_code(400);
        echo json_encode(['error' => 'Code invalide']);
        exit;
    }

    // Vérifier l'expiration
    if (strtotime($verification['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Code expiré. Veuillez demander un nouveau code.']);
        exit;
    }

    // Marquer le code comme utilisé
    $db->execute("UPDATE email_verifications SET used = TRUE WHERE id = ?", [$verification['id']]);

    // Marquer le compte comme vérifié
    $db->execute("UPDATE customers SET email_verified = TRUE WHERE email = ?", [$email]);

    // Récupérer les infos mises à jour du client
    $customerData = $customer->getById($customerData['id']);

    // Démarrer la session
    $session = Session::getInstance();
    $_SESSION['customer_id'] = $customerData['id'];
    $_SESSION['customer_email'] = $customerData['email'];
    $_SESSION['customer_name'] = $customerData['first_name'] . ' ' . $customerData['last_name'];

    // Supprimer les autres codes pour cet email
    $db->execute("DELETE FROM email_verifications WHERE email = ?", [$email]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Email vérifié avec succès. Bienvenue !',
        'customer' => $customerData
    ]);

} catch (Exception $e) {
    error_log("[VERIFY-EMAIL] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
