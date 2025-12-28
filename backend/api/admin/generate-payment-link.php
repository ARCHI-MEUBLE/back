<?php
/**
 * API Admin - Générer un lien de paiement sécurisé
 * POST /api/admin/generate-payment-link
 *
 * Permet à un admin de générer un lien de paiement pour une commande
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../models/PaymentLink.php';
require_once __DIR__ . '/../../models/Order.php';

try {
    // Vérifier l'authentification admin
    if (!isset($_SESSION['admin_email'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Non authentifié'
        ]);
        exit;
    }

    // Vérifier la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Méthode non autorisée'
        ]);
        exit;
    }

    // Récupérer les données de la requête
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['order_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de commande manquant'
        ]);
        exit;
    }

    $orderId = intval($data['order_id']);
    $expiryDays = isset($data['expiry_days']) ? intval($data['expiry_days']) : 30;
    $adminEmail = $_SESSION['admin_email'];

    // Générer le lien de paiement
    $paymentLink = new PaymentLink();
    $link = $paymentLink->generateLink($orderId, $adminEmail, $expiryDays);

    if (!$link) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erreur lors de la génération du lien'
        ]);
        exit;
    }

    // Construire l'URL complète du lien
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];

    // Pour le développement local, utiliser localhost:3000
    $baseUrl = 'http://localhost:3000';

    // En production, utiliser le domaine réel
    if (strpos($host, 'localhost') === false && strpos($host, '127.0.0.1') === false) {
        $baseUrl = $protocol . '://' . $host;
    }

    $fullUrl = $baseUrl . '/paiement/' . $link['token'];

    // Enregistrer une notification admin
    require_once __DIR__ . '/../../models/Notification.php';
    $notification = new Notification();
    $notification->create(
        'payment_link_created',
        'Lien de paiement généré',
        "Un lien de paiement a été généré pour la commande #" . $orderId,
        $orderId,
        null
    );

    // Envoyer un email au client avec le lien de paiement
    require_once __DIR__ . '/../../services/EmailService.php';
    require_once __DIR__ . '/../../core/Database.php';
    $emailService = new EmailService();
    $db = Database::getInstance();

    // Récupérer les détails de la commande avec les infos client
    $orderQuery = "SELECT o.*,
                          c.email as customer_email,
                          c.first_name,
                          c.last_name
                   FROM orders o
                   LEFT JOIN customers c ON o.customer_id = c.id
                   WHERE o.id = ?";
    $orderDetails = $db->queryOne($orderQuery, [$orderId]);

    if ($orderDetails && !empty($orderDetails['customer_email'])) {
        $customerName = trim(($orderDetails['first_name'] ?? '') . ' ' . ($orderDetails['last_name'] ?? ''));

        $emailService->sendPaymentLinkEmail(
            $orderDetails['customer_email'],
            $customerName ?: 'Client',
            $orderDetails['order_number'],
            $fullUrl,
            $link['expires_at'],
            $orderDetails['total_amount']
        );
    }

    // Retourner le lien créé
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $link['id'],
            'token' => $link['token'],
            'url' => $fullUrl,
            'expires_at' => $link['expires_at'],
            'order_id' => $orderId,
            'created_by' => $adminEmail
        ],
        'message' => 'Lien de paiement généré avec succès'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
