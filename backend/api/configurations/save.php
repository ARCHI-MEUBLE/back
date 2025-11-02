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

require_once __DIR__ . '/../../models/Configuration.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validation
    $required = ['prompt', 'price'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            http_response_code(400);
            echo json_encode(['error' => "Le champ $field est requis"]);
            exit;
        }
    }

    // Préparer les données de configuration (inclure le name dans config_data)
    $configData = $data['config_data'] ?? [];
    if (isset($data['name'])) {
        $configData['name'] = $data['name'];
    }
    if (isset($data['thumbnail_url'])) {
        $configData['thumbnail_url'] = $data['thumbnail_url'];
    }

    $config = new Configuration();

    // Mise à jour si un identifiant est fourni
    if (isset($data['id']) && $data['id']) {
        $configId = (int) $data['id'];
        $existing = $config->getById($configId);

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'Configuration introuvable']);
            exit;
        }

        if (strval($existing['user_id']) !== strval($_SESSION['customer_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            exit;
        }

        $updateData = [
            'config_string' => json_encode($configData),
            'prompt' => $data['prompt'],
            'price' => $data['price']
        ];

        if (array_key_exists('glb_url', $data)) {
            $updateData['glb_url'] = $data['glb_url'] !== '' ? $data['glb_url'] : null;
        }

        if (array_key_exists('model_id', $data)) {
            $updateData['template_id'] = $data['model_id'] ?: null;
        }

        $config->update($configId, $updateData);
        $savedConfiguration = $config->getById($configId);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Configuration mise à jour avec succès',
            'configuration' => $savedConfiguration
        ]);
        exit;
    }

    // Signature: create($userId, $templateId, $configString, $price, $glbUrl = null, $prompt = null, $userSession = null)
    $configId = $config->create(
        $_SESSION['customer_id'],
        $data['model_id'] ?? null,
        json_encode($configData),
        $data['price'],
        $data['glb_url'] ?? null,
        $data['prompt'],
        session_id()
    );
    
    // Récupérer la configuration créée
    $savedConfiguration = $config->getById($configId);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Configuration sauvegardée avec succès',
        'configuration' => $savedConfiguration
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
