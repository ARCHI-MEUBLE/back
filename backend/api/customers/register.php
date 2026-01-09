<?php
/**
 * API: Inscription client
 * POST /api/customers/register
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../models/Customer.php';

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
    
    // Vérifier si l'email existe déjà
    if ($customer->emailExists($data['email'])) {
        http_response_code(409);
        echo json_encode(['error' => 'Un compte avec cet email existe déjà']);
        exit;
    }
    
    // Créer le client
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
    
    // Récupérer les infos du client créé
    $customerData = $customer->getById($customerId);
    
    // Démarrer une session via le singleton
    require_once __DIR__ . '/../../core/Session.php';
    $session = Session::getInstance();
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['customer_email'] = $customerData['email'];
    $_SESSION['customer_name'] = $customerData['first_name'] . ' ' . $customerData['last_name'];
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Compte créé avec succès',
        'customer' => $customerData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
