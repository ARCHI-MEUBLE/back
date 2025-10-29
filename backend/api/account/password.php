<?php
/**
 * API: Changer le mot de passe client
 * PUT /api/account/password
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
    $db = Database::getInstance();
    $customerId = $_SESSION['customer_id'];

    // Récupérer les données
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Mot de passe actuel et nouveau mot de passe requis']);
        exit;
    }

    // Valider la longueur du nouveau mot de passe
    if (strlen($data['newPassword']) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Le nouveau mot de passe doit contenir au moins 6 caractères']);
        exit;
    }

    // Vérifier le mot de passe actuel
    $query = "SELECT password FROM customers WHERE id = :id";
    $customer = $db->queryOne($query, ['id' => $customerId]);

    if (!$customer || !password_verify($data['currentPassword'], $customer['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Mot de passe actuel incorrect']);
        exit;
    }

    // Hasher le nouveau mot de passe
    $newPasswordHash = password_hash($data['newPassword'], PASSWORD_BCRYPT);

    // Mettre à jour le mot de passe
    $updateQuery = "UPDATE customers SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $db->execute($updateQuery, [
        'password' => $newPasswordHash,
        'id' => $customerId
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Mot de passe mis à jour avec succès'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
