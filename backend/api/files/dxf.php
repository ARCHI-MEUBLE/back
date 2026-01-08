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
$session = Session::getInstance();
if (!$session->has('admin_email') || $session->get('is_admin') !== true) {
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

    // Check if a specific DXF file exists
    // The path in database is relative to public folder usually (e.g. /models/meuble_XYZ.dxf)
    // We need to point to front/public/... if it's there
    if ($config && $config['dxf_url']) {
        // Nettoyer l'URL au cas où elle contient le protocole
        $cleanPath = parse_url($config['dxf_url'], PHP_URL_PATH);
        
        // Try multiple possible paths
        $possiblePaths = [
            __DIR__ . '/../../../../front/public' . $cleanPath,
            __DIR__ . '/../../../' . $cleanPath,
            __DIR__ . '/../../../../public' . $cleanPath,
            __DIR__ . '/../../' . $cleanPath,
            '/app' . $cleanPath // Docker production
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $dxfPath = $path;
                error_log("DXF spécifique trouvé à : $path");
                break;
            }
        }
    }

    if (!isset($dxfPath)) {
        // Otherwise, use the generic DXF file or show error
        $dxfPath = __DIR__ . '/../../../pieces/piece_general.dxf';

        if (!file_exists($dxfPath)) {
            http_response_code(404);
            echo json_encode(['error' => 'DXF file not found. Please regenerate the configuration.']);
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
