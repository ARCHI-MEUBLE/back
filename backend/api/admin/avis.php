<?php
/**
 * API Admin: Gestion des avis clients
 * GET /api/admin/avis - Lister tous les avis (admin)
 * DELETE /api/admin/avis - Supprimer un avis (admin)
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Récupérer tous les avis
        $query = "SELECT * FROM avis ORDER BY created_at DESC";
        $avis = $db->query($query);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'avis' => $avis,
            'total' => count($avis)
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Supprimer un avis
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID requis']);
            exit;
        }

        $query = "DELETE FROM avis WHERE id = ?";
        $db->execute($query, [$data['id']]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Avis supprimé avec succès'
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
