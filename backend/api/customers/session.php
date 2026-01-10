<?php
/**
 * API: Session client
 * GET /api/customers/session - Vérifier la session
 * DELETE /api/customers/session - Se déconnecter
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Session.php';

$session = Session::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // SÉCURITÉ: Vérifier qu'il n'y a pas de session admin active
    if ($session->has('is_admin') && $session->get('is_admin') === true) {
        // Session admin détectée, pas de session customer
        http_response_code(200);
        echo json_encode(['authenticated' => false]);
        exit;
    }

    // Vérifier la session customer
    if ($session->has('customer_id')) {
        require_once __DIR__ . '/../../models/Customer.php';
        $customer = new Customer();
        $customerData = $customer->getById($session->get('customer_id'));

        if ($customerData) {
            http_response_code(200);
            echo json_encode([
                'authenticated' => true,
                'customer' => $customerData
            ]);
        } else {
            // Session invalide
            $session->destroy();
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
    $session->destroy();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Déconnexion réussie'
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
