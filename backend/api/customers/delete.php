<?php
/**
 * API: Supprimer le compte client
 * DELETE /api/customers/delete
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
    $customerId = $_SESSION['customer_id'];
    $db = Database::getInstance();

    // Supprimer le client (les commandes et configurations seront supprimées en cascade grâce aux FOREIGN KEY)
    $result = $db->execute(
        "DELETE FROM customers WHERE id = ?",
        [$customerId]
    );

    if ($result) {
        // Détruire la session
        session_destroy();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Compte supprimé avec succès'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la suppression du compte']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
