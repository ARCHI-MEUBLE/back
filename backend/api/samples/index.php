<?php
/**
 * API publique: Échantillons
 * GET /api/samples - Lister les types d'échantillons et couleurs, groupés par matériau
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../models/Sample.php';

/**
 * Convertit un chemin relatif d'image en URL complète
 */
function convertImagePath($imagePath) {
    if (!$imagePath) {
        return null;
    }

    // Si c'est déjà une URL complète, la retourner telle quelle
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }

    // S'assurer que le chemin commence par /
    if (strpos($imagePath, '/') !== 0) {
        $imagePath = '/' . $imagePath;
    }

    // Détecter l'environnement
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

    if ($isLocal) {
        // En local, on retourne un chemin relatif pour que Next.js le gère via son proxy
        return $imagePath;
    }

    // EN PRODUCTION: Les images sont sur Railway backend
    $protocol = 'https';
    $baseUrl = $protocol . '://' . $host;

    return $baseUrl . $imagePath;
}

// Forcer l'encodage UTF-8
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $sample = new Sample();

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $grouped = $sample->getGroupedByMaterial();

    // Convertir les chemins d'images en URLs complètes
    foreach ($grouped as $material => &$types) {
        foreach ($types as &$type) {
            if (isset($type['colors']) && is_array($type['colors'])) {
                foreach ($type['colors'] as &$color) {
                    if (isset($color['image_url'])) {
                        $color['image_url'] = convertImagePath($color['image_url']);
                    }
                }
            }
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'materials' => $grouped,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
