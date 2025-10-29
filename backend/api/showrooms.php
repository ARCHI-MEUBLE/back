<?php
/**
 * ArchiMeuble - Endpoint pour les showrooms
 * GET /api/showrooms           - Liste des showrooms
 * GET /api/showrooms/{id}      - Détail d'un showroom
 * (Évolutif) POST/PUT/DELETE   - Admin (à ajouter si besoin)
 *
 * Source de données: fichier JSON backend/data/showrooms.json
 * Date: 2025-10-28
 */

// Gestionnaire d'erreurs global pour retourner du JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur serveur',
        'message' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Erreur fatale',
            'message' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

require_once __DIR__ . '/../core/Cors.php';
Cors::enable();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($path, '/'));
// Format attendu: api/showrooms[/id]

$DATA_FILE = __DIR__ . '/../data/showrooms.json';

function read_showrooms($file) {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

header('Content-Type: application/json');

if ($method === 'GET') {
    $data = read_showrooms($DATA_FILE);

    // Détail par ID
    if (count($parts) >= 3 && $parts[0] === 'api' && $parts[1] === 'showrooms') {
        $id = $parts[2];
        $found = null;
        foreach ($data as $s) {
            if (isset($s['id']) && $s['id'] === $id) {
                $found = $s;
                break;
            }
        }
        if ($found) {
            http_response_code(200);
            echo json_encode($found);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Showroom non trouvé']);
        }
        exit;
    }

    // Liste complète
    http_response_code(200);
    echo json_encode($data);
    exit;
}

http_response_code(405);
header('Allow: GET');
echo json_encode(['error' => 'Méthode non autorisée']);
