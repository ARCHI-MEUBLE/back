<?php
/**
 * API Admin: Gestion des échantillons
 * GET /api/admin/samples                - Lister tous les types + couleurs
 * POST /api/admin/samples (JSON action) - CRUD types/couleurs
 *   { action: "create_type", name, material, description?, position? }
 *   { action: "update_type", id, ...fields }
 *   { action: "delete_type", id }
 *   { action: "create_color", type_id, name, hex?, image_url?, position? }
 *   { action: "update_color", id, ...fields }
 *   { action: "delete_color", id }
 */

require_once __DIR__ . '/../../config/cors.php';

// Forcer l'encodage UTF-8
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// L'authentification est gérée par Next.js API route
// Pas besoin de vérifier $_SESSION ici

require_once __DIR__ . '/../../models/Sample.php';

try {
    $sample = new Sample();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $all = $sample->getAllGroupedForAdmin();
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $all], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $input['action'] ?? '';

        switch ($action) {
            case 'create_type':
                if (empty($input['name']) || empty($input['material'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'name et material requis']);
                    exit;
                }
                $id = $sample->createType(
                    $input['name'], 
                    $input['material'], 
                    $input['description'] ?? null, 
                    $input['position'] ?? 0
                );
                if (!$id) throw new Exception('Échec création type');
                http_response_code(201);
                echo json_encode(['success' => true, 'id' => $id]);
                break;

            case 'update_type':
                if (empty($input['id'])) { http_response_code(400); echo json_encode(['error' => 'id requis']); exit; }
                $ok = $sample->updateType((int)$input['id'], $input);
                http_response_code($ok ? 200 : 500);
                echo json_encode(['success' => (bool)$ok]);
                break;

            case 'delete_type':
                if (empty($input['id'])) { http_response_code(400); echo json_encode(['error' => 'id requis']); exit; }
                $ok = $sample->deleteType((int)$input['id']);
                http_response_code($ok ? 200 : 500);
                echo json_encode(['success' => (bool)$ok]);
                break;

            case 'create_color':
                if (empty($input['type_id']) || empty($input['name'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'type_id et name requis']);
                    exit;
                }
                $cid = $sample->createColor(
                    (int)$input['type_id'], 
                    $input['name'], 
                    $input['hex'] ?? null, 
                    $input['image_url'] ?? null, 
                    $input['position'] ?? 0,
                    $input['price_per_m2'] ?? 0,
                    $input['unit_price'] ?? 0
                );
                if (!$cid) throw new Exception('Échec création couleur');
                http_response_code(201);
                echo json_encode(['success' => true, 'id' => $cid]);
                break;

            case 'update_color':
                if (empty($input['id'])) { http_response_code(400); echo json_encode(['error' => 'id requis']); exit; }
                $ok = $sample->updateColor((int)$input['id'], $input);
                http_response_code($ok ? 200 : 500);
                echo json_encode(['success' => (bool)$ok]);
                break;

            case 'delete_color':
                if (empty($input['id'])) { http_response_code(400); echo json_encode(['error' => 'id requis']); exit; }
                $ok = $sample->deleteColor((int)$input['id']);
                http_response_code($ok ? 200 : 500);
                echo json_encode(['success' => (bool)$ok]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'action invalide']);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
