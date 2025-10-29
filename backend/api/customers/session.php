<?php
/**
 * API: Session client
 * GET /api/customers/session - Vérifier la session
 * DELETE /api/customers/session - Se déconnecter
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Vérifier la session
    if (isset($_SESSION['customer_id'])) {
        require_once __DIR__ . '/../../models/Customer.php';
        $customer = new Customer();
        $customerData = $customer->getById($_SESSION['customer_id']);

        if ($customerData) {
            http_response_code(200);
            echo json_encode([
                'authenticated' => true,
                'customer' => $customerData
            ]);
        } else {
            // Session invalide
            session_destroy();
            http_response_code(200);
            echo json_encode(['authenticated' => false]);
        }
    } else {
        // Pas de session customer (peut-être admin ou anonyme)
        http_response_code(200);
        echo json_encode(['authenticated' => false]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Déconnexion
    session_destroy();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Déconnexion réussie'
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
