<?php
/**
 * API: Sauvegarder une configuration
 * POST /api/configurations/save
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../core/Session.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$session = Session::getInstance();

// Vérifier l'authentification (Client OU Admin)
error_log("SAVE.PHP - Session data: " . print_r($session->all(), true));

$isAdmin = $session->has('admin_email') && $session->get('is_admin') === true;
$userId = $session->get('customer_id');

// Lire le body pour vérifier si c'est une mise à jour
$inputRaw = file_get_contents('php://input');
$inputData = json_decode($inputRaw, true);
$isUpdate = isset($inputData['id']) && $inputData['id'];

// SÉCURITÉ: Pour les mises à jour, un admin peut modifier sans customer_id
// Pour les créations, un customer_id est requis
if (!$isUpdate && !$session->has('customer_id')) {
    error_log("SAVE.PHP - Unauthorized: No customer_id in session for new configuration");
    http_response_code(401);
    echo json_encode([
        'error' => 'Vous devez être connecté en tant que client pour créer une configuration'
    ]);
    exit;
}

// Pour les mises à jour, vérifier qu'on est soit admin, soit le propriétaire
if ($isUpdate && !$isAdmin && !$session->has('customer_id')) {
    error_log("SAVE.PHP - Unauthorized: Neither admin nor customer for update");
    http_response_code(401);
    echo json_encode([
        'error' => 'Vous devez être connecté pour modifier cette configuration'
    ]);
    exit;
}

error_log("SAVE.PHP - Authenticated: isAdmin=" . ($isAdmin ? 'YES' : 'NO') . ", userId=" . ($userId ?: 'NONE') . ", isUpdate=" . ($isUpdate ? 'YES' : 'NO'));

require_once __DIR__ . '/../../models/Configuration.php';

try {
    // Utiliser les données déjà parsées plus haut
    error_log("SAVE.PHP - Raw input: " . $inputRaw);
    $data = $inputData;

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

        // Préparer la réponse
        $responseData = [
            'success' => true,
            'message' => 'Configuration mise à jour avec succès',
            'configuration' => $savedConfiguration
        ];

        // NOTE: Pas d'email de notification pour les mises à jour de configuration
        // L'email "Nouveau projet client" est uniquement envoyé lors de la création
        http_response_code(200);
        echo json_encode($responseData);
        exit;
    }

    // Créer la configuration avec dxf_url si fourni
    error_log("SAVE.PHP - Creating new configuration for user: " . ($userId ?: 'GUEST'));
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

    if (!$configId) {
        error_log("SAVE.PHP - FAILED to create configuration in database");
        throw new Exception("Erreur lors de l'insertion en base de données");
    }
    
    error_log("SAVE.PHP - Configuration created with ID: $configId");

    // Mettre à jour le dxf_url si fourni
    if (isset($data['dxf_url']) && $data['dxf_url'] !== '') {
        $config->update($configId, ['dxf_url' => $data['dxf_url']]);
    }
    
    // Récupérer la configuration créée
    $savedConfiguration = $config->getById($configId);

    // Préparer la réponse
    $responseData = [
        'success' => true,
        'message' => 'Configuration sauvegardée avec succès',
        'configuration' => $savedConfiguration
    ];

    // RÉPONSE RAPIDE AU CLIENT (Découplage de l'email)
    if (function_exists('fastcgi_finish_request')) {
        http_response_code(201);
        echo json_encode($responseData);
        session_write_close();
        fastcgi_finish_request();

        // Notification Admin (après libération du client)
        try {
            require_once __DIR__ . '/../../models/Customer.php';
            require_once __DIR__ . '/../../services/EmailService.php';

            $emailService = new EmailService();
            $customer = null;
            if ($userId) {
                $customerModel = new Customer();
                $customer = $customerModel->getById($userId);
            }
            if (!$customer) {
                $customer = [
                    'first_name' => $isAdmin ? 'Admin' : 'Visiteur',
                    'last_name' => $session->get('admin_email') ?? 'Système',
                    'email' => $session->get('admin_email') ?? 'noreply@archimeuble.com',
                    'phone' => ''
                ];
            }
            $emailService->sendNewConfigurationNotificationToAdmin($savedConfiguration, $customer);
        } catch (Exception $e) {
            error_log("Failed to send admin notification (create): " . $e->getMessage());
        }
        exit; // Important: ne pas continuer après fastcgi
    }

    // Sans fastcgi: notification puis réponse
    try {
        require_once __DIR__ . '/../../models/Customer.php';
        require_once __DIR__ . '/../../services/EmailService.php';

        $emailService = new EmailService();
        $customer = null;
        if ($userId) {
            $customerModel = new Customer();
            $customer = $customerModel->getById($userId);
        }
        if (!$customer) {
            $customer = [
                'first_name' => $isAdmin ? 'Admin' : 'Visiteur',
                'last_name' => $session->get('admin_email') ?? 'Système',
                'email' => $session->get('admin_email') ?? 'noreply@archimeuble.com',
                'phone' => ''
            ];
        }
        $emailService->sendNewConfigurationNotificationToAdmin($savedConfiguration, $customer);
    } catch (Exception $e) {
        error_log("Failed to send admin notification (create): " . $e->getMessage());
    }

    http_response_code(201);
    echo json_encode($responseData);
    
} catch (Exception $e) {
    error_log("CRITICAL ERROR in save.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur interne du serveur',
        'message' => $e->getMessage(),
        'debug_hint' => 'Consultez les logs du serveur pour plus de détails'
    ]);
}
