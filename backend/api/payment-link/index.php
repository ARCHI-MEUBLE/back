<?php
/**
 * API Publique - Récupérer les détails d'une commande via lien de paiement
 * GET /api/payment-link/{token}
 *
 * Accessible publiquement (pas d'authentification requise)
 * Sécurisé par le token unique
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../models/PaymentLink.php';

try {
    // Vérifier la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Méthode non autorisée'
        ]);
        exit;
    }

    // Récupérer le token depuis l'URL
    // Format: /api/payment-link/index.php?token=xxx
    $token = isset($_GET['token']) ? trim($_GET['token']) : '';

    if (empty($token)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Token manquant'
        ]);
        exit;
    }

    // Instancier le modèle
    $paymentLink = new PaymentLink();

    // Valider le lien
    $validation = $paymentLink->validateLink($token);

    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $validation['message'],
            'expired' => strpos($validation['message'], 'expiré') !== false,
            'used' => strpos($validation['message'], 'utilisé') !== false,
            'revoked' => strpos($validation['message'], 'révoqué') !== false
        ]);
        exit;
    }

    // Marquer le lien comme consulté (si première visite)
    $paymentLink->markAsAccessed($token);

    // Récupérer les détails complets de la commande
    $orderData = $paymentLink->getOrderItemsByToken($token);

    if (!$orderData) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Commande introuvable'
        ]);
        exit;
    }

    // Préparer les données à retourner (sans informations sensibles)
    $response = [
        'success' => true,
        'data' => [
            'order' => [
                'order_number' => $orderData['order']['order_number'],
                'total_amount' => floatval($orderData['order']['total_amount']),
                'status' => $orderData['order']['order_status'],
                'payment_status' => $orderData['order']['payment_status'],
                'created_at' => $orderData['order']['order_created_at'],
                'shipping_address' => $orderData['order']['shipping_address'],
                'billing_address' => $orderData['order']['billing_address']
            ],
            'customer' => [
                'first_name' => $orderData['order']['first_name'],
                'last_name' => $orderData['order']['last_name'],
                'email' => $orderData['order']['email'],
                'phone' => $orderData['order']['phone']
            ],
            'items' => [
                'configurations' => array_map(function($item) {
                    return [
                        'id' => $item['id'],
                        'name' => $item['config_name'] ?? 'Configuration personnalisée',
                        'prompt' => $item['prompt'],
                        'quantity' => intval($item['quantity']),
                        'unit_price' => floatval($item['unit_price']),
                        'total_price' => floatval($item['total_price']),
                        'thumbnail_url' => $item['thumbnail_url'],
                        'config_data' => json_decode($item['config_data'], true)
                    ];
                }, $orderData['configurations']),
                'samples' => array_map(function($sample) {
                    return [
                        'id' => $sample['id'],
                        'name' => $sample['sample_name'],
                        'type' => $sample['sample_type_name'],
                        'material' => $sample['material'],
                        'hex' => $sample['hex'],
                        'image_url' => $sample['image_url'],
                        'quantity' => intval($sample['quantity']),
                        'price' => floatval($sample['price'])
                    ];
                }, $orderData['samples'])
            ],
            'payment_link' => [
                'token' => $orderData['order']['token'],
                'expires_at' => $orderData['order']['expires_at'],
                'status' => $orderData['order']['status']
            ]
        ]
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
