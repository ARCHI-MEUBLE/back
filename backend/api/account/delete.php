<?php
/**
 * API: Supprimer le compte client
 * DELETE /api/account/delete
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

    // Récupérer les données pour validation (mot de passe requis pour suppression)
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Mot de passe requis pour confirmer la suppression']);
        exit;
    }

    // Vérifier le mot de passe
    $query = "SELECT password FROM customers WHERE id = :id";
    $customer = $db->queryOne($query, ['id' => $customerId]);

    if (!$customer || !password_verify($data['password'], $customer['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Mot de passe incorrect']);
        exit;
    }

    // Supprimer le compte (les FK ON DELETE CASCADE s'occuperont des données liées)
    $deleteQuery = "DELETE FROM customers WHERE id = :id";
    $db->execute($deleteQuery, ['id' => $customerId]);

    // Détruire la session
    session_destroy();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Compte supprimé avec succès'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
