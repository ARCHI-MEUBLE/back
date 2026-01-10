<?php
/**
 * API: Connexion client
 * POST /api/customers/login
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../models/Customer.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email et mot de passe requis']);
        exit;
    }
    
    $customer = new Customer();
    $customerData = $customer->verifyCredentials($data['email'], $data['password']);
    
    if (!$customerData) {
        http_response_code(401);
        echo json_encode(['error' => 'Email ou mot de passe incorrect']);
        exit;
    }

    // Utiliser la classe Session (sécurisée)
    $session = Session::getInstance();

    // Régénérer l'ID de session pour prévenir la fixation de session
    $session->regenerate();

    // SÉCURITÉ: Effacer toutes les données admin si présentes
    $session->remove('admin_id');
    $session->remove('admin_email');
    $session->remove('is_admin');

    // Créer la session customer
    $session->set('customer_id', $customerData['id']);
    $session->set('customer_email', $customerData['email']);
    $session->set('customer_name', $customerData['first_name'] . ' ' . $customerData['last_name']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'customer' => $customerData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
