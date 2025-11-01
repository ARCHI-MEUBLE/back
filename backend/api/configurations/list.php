<?php
/**
 * API: Lister mes configurations
 * GET /api/configurations/list
 * DELETE /api/configurations/{id} - Supprimer une configuration
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../models/Configuration.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Lister les configurations
        $config = new Configuration();

        // Récupérer toutes les configurations du client (pas de pagination pour l'instant)
        $configurations = $config->getByUserId($_SESSION['customer_id']);
        $total = $config->countByUserId($_SESSION['customer_id']);

        http_response_code(200);
        echo json_encode([
            'configurations' => $configurations,
            'total' => $total
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Supprimer une configuration
        // URL: /api/configurations/list?id=123
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID requis']);
            exit;
        }

        $config = new Configuration();

        // Vérifier que la config appartient au client (attention aux types int vs string)
        $existingConfig = $config->getById($_GET['id']);
        if (
            !$existingConfig ||
            strval($existingConfig['user_id']) !== strval($_SESSION['customer_id'])
        ) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            exit;
        }

        $config->delete($_GET['id']);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Configuration supprimée'
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
