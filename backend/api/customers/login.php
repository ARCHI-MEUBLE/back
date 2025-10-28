<?php
/**
 * API: Connexion client
 * POST /api/customers/login
 */

require_once __DIR__ . '/../../config/cors.php';

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
    $customerData = $customer->authenticate($data['email'], $data['password']);
    
    if (!$customerData) {
        http_response_code(401);
        echo json_encode(['error' => 'Email ou mot de passe incorrect']);
        exit;
    }
    
    // La session est déjà démarrée par cors.php
    $_SESSION['customer_id'] = $customerData['id'];
    $_SESSION['customer_email'] = $customerData['email'];
    $_SESSION['customer_name'] = $customerData['first_name'] . ' ' . $customerData['last_name'];
    
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
