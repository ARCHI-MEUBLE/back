<?php
/**
 * API Publique - Vérifier et confirmer un paiement Stripe
 * POST /api/payment-link/verify-payment
 *
 * Accessible publiquement, vérifie le PaymentIntent auprès de Stripe
 * et met à jour le statut de la commande
 */

// Désactiver l'affichage des erreurs PHP pour ne pas casser le JSON
error_reporting(0);
ini_set('display_errors', '0');

// Charger les variables d'environnement depuis .env (DOIT être en premier)
require_once __DIR__ . '/../../config/env.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Méthode non autorisée'
    ]);
    exit;
}

try {
    // Récupérer les données
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['payment_intent_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'payment_intent_id manquant'
        ]);
        exit;
    }

    $paymentIntentId = trim($data['payment_intent_id']);

    // Charger Stripe
    require_once __DIR__ . '/../../../vendor/stripe/init.php';

    $stripeSecretKey = getenv('STRIPE_SECRET_KEY');

    if (!$stripeSecretKey || $stripeSecretKey === 'sk_test_YOUR_SECRET_KEY_HERE') {
        throw new Exception('Clé Stripe non configurée');
    }

    \Stripe\Stripe::setApiKey($stripeSecretKey);

    // Récupérer le PaymentIntent depuis Stripe
    $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

    // Vérifier le statut
    if ($paymentIntent->status !== 'succeeded') {
        echo json_encode([
            'success' => false,
            'error' => 'Paiement non confirmé',
            'status' => $paymentIntent->status
        ]);
        exit;
    }

    // Récupérer l'order_id depuis les metadata
    $orderId = $paymentIntent->metadata->order_id ?? null;
    $paymentLinkToken = $paymentIntent->metadata->payment_link_token ?? null;

    if (!$orderId) {
        throw new Exception('Commande introuvable dans les metadata');
    }

    require_once __DIR__ . '/../../core/Database.php';
    require_once __DIR__ . '/../../models/Order.php';
    require_once __DIR__ . '/../../models/Customer.php';
    require_once __DIR__ . '/../../models/Cart.php';
    require_once __DIR__ . '/../../services/EmailService.php';
    require_once __DIR__ . '/../../services/InvoiceService.php';

    $db = Database::getInstance();
    $orderModel = new Order();
    $customerModel = new Customer();
    $cart = new Cart();
    $emailService = new EmailService();
    $invoiceService = new InvoiceService();

    // Vérifier si la commande existe
    $order = $db->queryOne("SELECT * FROM orders WHERE id = ?", [$orderId]);

    if (!$order) {
        throw new Exception('Commande introuvable');
    }

    // Vérifier si déjà payée (pour éviter les doublons)
    if ($order['payment_status'] === 'paid') {
        echo json_encode([
            'success' => true,
            'message' => 'Commande déjà confirmée',
            'order_id' => $orderId,
            'already_paid' => true
        ]);
        exit;
    }

    // Mettre à jour le statut de paiement
    $updateQuery = "UPDATE orders
                   SET payment_status = 'paid',
                       status = 'confirmed',
                       stripe_payment_intent_id = ?,
                       confirmed_at = CURRENT_TIMESTAMP,
                       updated_at = CURRENT_TIMESTAMP
                   WHERE id = ?";

    $db->execute($updateQuery, [$paymentIntentId, $orderId]);

    // Vider le panier du client
    $cart->clear($order['customer_id']);

    // Récupérer les détails complets
    $fullOrder = $orderModel->getById($orderId);
    $customer = $customerModel->getById($order['customer_id']);
    $orderItems = $orderModel->getOrderItems($orderId);

    // Envoyer emails
    try {
        $emailService->sendOrderConfirmation($fullOrder, $customer, $orderItems);
        $emailService->sendNewOrderNotificationToAdmin($fullOrder, $customer, $orderItems);
    } catch (Exception $e) {
        error_log("Failed to send emails for order #{$orderId}: " . $e->getMessage());
    }

    // Marquer le lien de paiement comme utilisé
    if ($paymentLinkToken) {
        require_once __DIR__ . '/../../models/PaymentLink.php';
        $paymentLinkModel = new PaymentLink();
        $paymentLinkModel->markAsUsed($paymentLinkToken);
    }

    // Générer la facture
    try {
        $invoice = $invoiceService->generateInvoice($fullOrder, $customer, $orderItems);
        error_log("Invoice generated: {$invoice['filename']} for order ID: {$orderId}");
    } catch (Exception $e) {
        error_log("Failed to generate invoice for order ID: {$orderId}: " . $e->getMessage());
    }

    // Créer une notification admin
    require_once __DIR__ . '/../../models/AdminNotification.php';
    $notification = new AdminNotification();
    $notification->create(
        'payment',
        "Paiement confirmé pour la commande #{$orderId}",
        $orderId
    );

    error_log("Payment verified and confirmed for order ID: {$orderId}");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Paiement confirmé avec succès',
        'order_id' => $orderId,
        'order_number' => $fullOrder['order_number']
    ]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Stripe API error in verify-payment: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur Stripe: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in verify-payment: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
