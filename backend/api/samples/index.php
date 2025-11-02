<?php
/**
 * API publique: Échantillons
 * GET /api/samples - Lister les types d'échantillons et couleurs, groupés par matériau
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../models/Sample.php';

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
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'materials' => $grouped,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
