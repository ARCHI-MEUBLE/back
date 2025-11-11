<?php
/**
 * API: Télécharger un fichier DXF pour une configuration
 * GET /api/files/dxf?id=<config_id>
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
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
    $configId = $_GET['id'] ?? null;

    if (!$configId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de configuration requis']);
        exit;
    }

    // Essayer de récupérer la configuration pour un fichier DXF spécifique
    $db = Database::getInstance();
    $config = $db->queryOne(
        "SELECT id, dxf_url, prompt FROM configurations WHERE id = ?",
        [$configId]
    );

    // Vérifier si un fichier DXF spécifique existe
    if ($config && $config['dxf_url'] && file_exists(__DIR__ . '/../../../' . $config['dxf_url'])) {
        $dxfPath = __DIR__ . '/../../../' . $config['dxf_url'];
    } else {
        // Sinon, utiliser le fichier DXF générique
        $dxfPath = __DIR__ . '/../../../pieces/piece_general.dxf';

        if (!file_exists($dxfPath)) {
            http_response_code(404);
            echo json_encode(['error' => 'Fichier DXF non trouvé']);
            exit;
        }
    }

    // Envoyer le fichier DXF
    header('Content-Type: application/dxf');
    header('Content-Disposition: attachment; filename="configuration_' . $configId . '.dxf"');
    header('Content-Length: ' . filesize($dxfPath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    readfile($dxfPath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
