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

// Vérifier l'authentification (Client OU Admin)
if (!isset($_SESSION['customer_id']) && !isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$isAdmin = isset($_SESSION['admin_email']);
$userId = $_SESSION['customer_id'] ?? null;

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

        // L'admin peut tout modifier, le client seulement sa config
        if (!$isAdmin && strval($existing['user_id']) !== strval($userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            exit;
        }

        $updateData = [
            'config_string' => json_encode($configData),
            'prompt' => $data['prompt'],
            'price' => $data['price']
        ];

        // Pour un client, toute modification remet le statut en attente
        // Pour un admin, on garde le statut actuel ou on utilise celui passé en paramètre
        if (!$isAdmin) {
            $updateData['status'] = 'en_attente_validation';
        } elseif (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (array_key_exists('glb_url', $data)) {
            $updateData['glb_url'] = $data['glb_url'] !== '' ? $data['glb_url'] : null;
        }

        if (array_key_exists('dxf_url', $data)) {
            $updateData['dxf_url'] = $data['dxf_url'] !== '' ? $data['dxf_url'] : null;
        }

        if (array_key_exists('model_id', $data)) {
            $updateData['template_id'] = $data['model_id'] ?: null;
        }

        $config->update($configId, $updateData);
        $savedConfiguration = $config->getById($configId);

        // Notification Admin
        try {
            error_log("Triggering admin notification for config update (ID: $configId). UserID: " . ($userId ?: 'ADMIN'));
            require_once __DIR__ . '/../../models/Customer.php';
            require_once __DIR__ . '/../../services/EmailService.php';
            
            $emailService = new EmailService();
            
            // Si c'est un client, on récupère ses infos
            $customer = null;
            if ($userId) {
                $customerModel = new Customer();
                $customer = $customerModel->getById($userId);
            }
            
            // Si pas de client (ex: admin qui crée), on simule un client avec les infos de session ou génériques
            if (!$customer) {
                $customer = [
                    'first_name' => $isAdmin ? 'Admin' : 'Visiteur',
                    'last_name' => $_SESSION['admin_email'] ?? 'Système',
                    'email' => $_SESSION['admin_email'] ?? 'noreply@archimeuble.com',
                    'phone' => ''
                ];
            }

            $sent = $emailService->sendNewConfigurationNotificationToAdmin($savedConfiguration, $customer);
            error_log("Notification sent result: " . ($sent ? 'SUCCESS' : 'FAILURE'));
        } catch (Exception $e) {
            error_log("Failed to send admin notification (update): " . $e->getMessage());
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Configuration mise à jour avec succès',
            'configuration' => $savedConfiguration
        ]);
        exit;
    }

    // Créer la configuration avec dxf_url si fourni
    $configId = $config->create(
        $userId,
        $data['model_id'] ?? null,
        json_encode($configData),
        $data['price'],
        $data['glb_url'] ?? null,
        $data['prompt'],
        session_id(),
        $isAdmin && isset($data['status']) ? $data['status'] : 'en_attente_validation'
    );

    // Mettre à jour le dxf_url si fourni
    if (isset($data['dxf_url']) && $data['dxf_url'] !== '') {
        $config->update($configId, ['dxf_url' => $data['dxf_url']]);
    }
    
    // Récupérer la configuration créée
    $savedConfiguration = $config->getById($configId);

    // Notification Admin
    try {
        error_log("Triggering admin notification for new config (ID: $configId). UserID: " . ($userId ?: 'ADMIN'));
        require_once __DIR__ . '/../../models/Customer.php';
        require_once __DIR__ . '/../../services/EmailService.php';
        
        $emailService = new EmailService();
        
        // Si c'est un client, on récupère ses infos
        $customer = null;
        if ($userId) {
            $customerModel = new Customer();
            $customer = $customerModel->getById($userId);
        }
        
        // Si pas de client (ex: admin qui crée), on simule un client avec les infos de session ou génériques
        if (!$customer) {
            $customer = [
                'first_name' => $isAdmin ? 'Admin' : 'Visiteur',
                'last_name' => $_SESSION['admin_email'] ?? 'Système',
                'email' => $_SESSION['admin_email'] ?? 'noreply@archimeuble.com',
                'phone' => ''
            ];
        }

        $sent = $emailService->sendNewConfigurationNotificationToAdmin($savedConfiguration, $customer);
        error_log("Notification sent result: " . ($sent ? 'SUCCESS' : 'FAILURE'));
    } catch (Exception $e) {
        error_log("Failed to send admin notification (create): " . $e->getMessage());
    }
    
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
