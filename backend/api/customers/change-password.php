<?php
/**
 * API: Changer le mot de passe du client
 * PUT /api/customers/change-password
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Vérifier l'authentification
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $customerId = $_SESSION['customer_id'];
    $db = Database::getInstance();

    // Validation
    if (empty($data['current_password']) || empty($data['new_password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Mot de passe actuel et nouveau mot de passe requis']);
        exit;
    }

    // Vérifier la longueur du nouveau mot de passe
    if (strlen($data['new_password']) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Le nouveau mot de passe doit contenir au moins 6 caractères']);
        exit;
    }

    // Récupérer le client
    $customer = $db->queryOne(
        "SELECT * FROM customers WHERE id = ?",
        [$customerId]
    );

    if (!$customer) {
        http_response_code(404);
        echo json_encode(['error' => 'Client non trouvé']);
        exit;
    }

    // Vérifier le mot de passe actuel
    if (!password_verify($data['current_password'], $customer['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Mot de passe actuel incorrect']);
        exit;
    }

    // Hasher le nouveau mot de passe
    $newPasswordHash = password_hash($data['new_password'], PASSWORD_BCRYPT);

    // Mettre à jour le mot de passe
    $result = $db->execute(
        "UPDATE customers SET password = ? WHERE id = ?",
        [$newPasswordHash, $customerId]
    );

    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la mise à jour du mot de passe']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
