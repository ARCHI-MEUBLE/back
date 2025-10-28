<?php
/**
 * API: Sauvegarder une configuration
 * POST /api/configurations/save
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

require_once __DIR__ . '/../../models/SavedConfiguration.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    $required = ['name', 'prompt', 'price'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            http_response_code(400);
            echo json_encode(['error' => "Le champ $field est requis"]);
            exit;
        }
    }
    
    $savedConfig = new SavedConfiguration();
    
    $configId = $savedConfig->create(
        $_SESSION['customer_id'],
        $data['name'],
        $data['prompt'],
        json_encode($data['config_data'] ?? []),
        $data['price'],
        $data['model_id'] ?? null,
        $data['glb_url'] ?? null,
        $data['thumbnail_url'] ?? null
    );
    
    // Récupérer la configuration créée
    $config = $savedConfig->getById($configId);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Configuration sauvegardée avec succès',
        'configuration' => $config
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
