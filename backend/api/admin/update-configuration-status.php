<?php
/**
 * API Admin: Mise à jour du statut d'une configuration
 * POST /api/admin/update-configuration-status - Changer le statut d'une configuration
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['id']) || !isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID et status requis']);
            exit;
        }

        $id = (int)$input['id'];
        $status = $input['status'];

        // Valider le statut
        $validStatuses = ['en_attente_validation', 'validee', 'payee', 'en_production', 'livree', 'annulee'];
        if (!in_array($status, $validStatuses)) {
            http_response_code(400);
            echo json_encode(['error' => 'Statut invalide']);
            exit;
        }

        $db = Database::getInstance();

        // Vérifier que la configuration existe
        $config = $db->queryOne("SELECT id FROM configurations WHERE id = ?", [$id]);
        if (!$config) {
            http_response_code(404);
            echo json_encode(['error' => 'Configuration introuvable']);
            exit;
        }

        // Mettre à jour le statut
        $updated = $db->execute(
            "UPDATE configurations SET status = ? WHERE id = ?",
            [$status, $id]
        );

        if ($updated) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Statut mis à jour',
                'id' => $id,
                'status' => $status
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la mise à jour']);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
