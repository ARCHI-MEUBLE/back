<?php
/**
 * API Publique - Vérifier le statut d'un paiement et mettre à jour la base de données
 * POST /api/payment-link/verify-payment
 *
 * Cette API est appelée automatiquement après un paiement pour vérifier
 * le statut directement auprès de Stripe, en cas d'échec du webhook
 */

// Charger les variables d'environnement depuis .env
require_once __DIR__ . '/../../config/env.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://127.0.0.1:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
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

    if (!isset($data['payment_intent_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Payment Intent ID manquant'
        ]);
        exit;
    }

    $paymentIntentId = trim($data['payment_intent_id']);

    // Charger la librairie Stripe
    require_once __DIR__ . '/../../../vendor/stripe/init.php';

    // Récupérer la clé secrète Stripe depuis .env
    $stripeSecretKey = getenv('STRIPE_SECRET_KEY');

    if (!$stripeSecretKey || $stripeSecretKey === 'sk_test_YOUR_SECRET_KEY_HERE') {
        throw new Exception('Clé Stripe non configurée');
    }

    \Stripe\Stripe::setApiKey($stripeSecretKey);

    // Récupérer le PaymentIntent depuis Stripe
    $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

    error_log("VERIFY-PAYMENT: Checking PI {$paymentIntentId}, status: {$paymentIntent->status}");

    // Si le paiement n'est pas réussi, retourner l'état
    if ($paymentIntent->status !== 'succeeded') {
        echo json_encode([
            'success' => false,
            'status' => $paymentIntent->status,
            'message' => 'Paiement non confirmé'
        ]);
        exit;
    }

    // Le paiement est réussi, mettre à jour la base de données
    require_once __DIR__ . '/../../core/Database.php';
    require_once __DIR__ . '/../../models/Order.php';
    require_once __DIR__ . '/../../models/Cart.php';
    require_once __DIR__ . '/../../models/Customer.php';
    require_once __DIR__ . '/../../services/EmailService.php';
    require_once __DIR__ . '/../../services/InvoiceService.php';

    $db = Database::getInstance();
    $orderModel = new Order();
    $cart = new Cart();
    $customerModel = new Customer();
    $emailService = new EmailService();
    $invoiceService = new InvoiceService();

    // Récupérer les métadonnées
    $metadata = $paymentIntent->metadata ? $paymentIntent->metadata->toArray() : [];
    $paymentType = $metadata['payment_type'] ?? 'full';
    $paymentLinkToken = $metadata['payment_link_token'] ?? null;
    $orderId = $metadata['order_id'] ?? null;

    error_log("VERIFY-PAYMENT: Metadata - Type: {$paymentType}, Order ID: {$orderId}, Token: {$paymentLinkToken}");

    // Trouver la commande associée
    $order = null;
    if ($orderId) {
        $query = "SELECT * FROM orders WHERE id = ?";
        $order = $db->queryOne($query, [$orderId]);
        if ($order) {
            error_log("VERIFY-PAYMENT: Found order #{$order['order_number']} (ID: {$order['id']}) via metadata order_id");
        }
    }

    if (!$order) {
        $query = "SELECT * FROM orders WHERE deposit_stripe_intent_id = ?";
        $order = $db->queryOne($query, [$paymentIntentId]);
        if ($order) {
            $paymentType = 'deposit';
            error_log("VERIFY-PAYMENT: Found order #{$order['order_number']} (ID: {$order['id']}) via deposit_stripe_intent_id");
        }
    }

    if (!$order) {
        $query = "SELECT * FROM orders WHERE balance_stripe_intent_id = ?";
        $order = $db->queryOne($query, [$paymentIntentId]);
        if ($order) {
            $paymentType = 'balance';
            error_log("VERIFY-PAYMENT: Found order #{$order['order_number']} (ID: {$order['id']}) via balance_stripe_intent_id");
        }
    }

    if (!$order) {
        $query = "SELECT * FROM orders WHERE stripe_payment_intent_id = ?";
        $order = $db->queryOne($query, [$paymentIntentId]);
        if ($order) {
            $paymentType = 'full';
            error_log("VERIFY-PAYMENT: Found order #{$order['order_number']} (ID: {$order['id']}) via stripe_payment_intent_id");
        }
    }

    if (!$order) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Commande introuvable pour ce paiement'
        ]);
        exit;
    }

    // Vérifier si le paiement a déjà été traité
    $alreadyProcessed = false;
    if ($paymentType === 'deposit' && ($order['deposit_payment_status'] ?? 'pending') === 'paid') {
        $alreadyProcessed = true;
        error_log("VERIFY-PAYMENT DEBUG: Deposit already processed for order #{$order['id']}");
    } elseif ($paymentType === 'balance' && ($order['balance_payment_status'] ?? 'pending') === 'paid') {
        $alreadyProcessed = true;
        error_log("VERIFY-PAYMENT DEBUG: Balance already processed for order #{$order['id']}");
    } elseif ($paymentType === 'full' && ($order['payment_status'] ?? 'pending') === 'paid') {
        $alreadyProcessed = true;
        error_log("VERIFY-PAYMENT DEBUG: Full payment already processed for order #{$order['id']}");
    }

    if (!$alreadyProcessed) {
        error_log("VERIFY-PAYMENT DEBUG: Order not already processed. Updating database...");
        // Mettre à jour le statut de paiement selon le type
        if ($paymentType === 'deposit') {
            error_log("VERIFY-PAYMENT DEBUG: Updating as deposit paid");
            $updateQuery = "UPDATE orders
                           SET deposit_payment_status = 'paid',
                               payment_status = 'partially_paid',
                               status = 'confirmed',
                               deposit_stripe_intent_id = ?,
                               confirmed_at = CURRENT_TIMESTAMP,
                               updated_at = CURRENT_TIMESTAMP
                           WHERE id = ?";
            $db->execute($updateQuery, [$paymentIntentId, $order['id']]);
        } elseif ($paymentType === 'balance') {
            error_log("VERIFY-PAYMENT DEBUG: Updating as balance paid");
            $updateQuery = "UPDATE orders
                           SET balance_payment_status = 'paid',
                               payment_status = 'paid',
                               balance_stripe_intent_id = ?,
                               updated_at = CURRENT_TIMESTAMP
                           WHERE id = ?";
            $db->execute($updateQuery, [$paymentIntentId, $order['id']]);
        } else {
            error_log("VERIFY-PAYMENT DEBUG: Updating as full payment");
            $updateQuery = "UPDATE orders
                           SET payment_status = 'paid',
                               status = 'confirmed',
                               stripe_payment_intent_id = ?,
                               confirmed_at = CURRENT_TIMESTAMP,
                               updated_at = CURRENT_TIMESTAMP
                           WHERE id = ?";
            $db->execute($updateQuery, [$paymentIntentId, $order['id']]);
        }
        error_log("VERIFY-PAYMENT DEBUG: Database update successful");

        // Vider le panier uniquement sur premier paiement (full ou deposit)
        if ($paymentType === 'full' || $paymentType === 'deposit') {
            error_log("VERIFY-PAYMENT DEBUG: Clearing cart for customer {$order['customer_id']}");
            $cart->clear($order['customer_id']);
        }

        // Récupérer les détails pour l'email
        error_log("VERIFY-PAYMENT DEBUG: Fetching order details for emails...");
        $customer = $customerModel->getById($order['customer_id']);
        $orderItems = $orderModel->getOrderItems($order['id']);
        $fullOrder = $orderModel->getById($order['id']);
        
        if (!$customer || !$fullOrder) {
            error_log("VERIFY-PAYMENT ERROR: Customer or FullOrder not found!");
            throw new Exception("Erreur lors de la récupération des données de commande");
        }

        $orderNumber = $fullOrder['order_number'];
        error_log("VERIFY-PAYMENT DEBUG: Sending confirmation for order #{$orderNumber}. Type: {$paymentType}");

        // Envoyer l'email de confirmation
        error_log("VERIFY-PAYMENT DEBUG: Calling EmailService::sendOrderConfirmation");
        $emailStatus = $emailService->sendOrderConfirmation($fullOrder, $customer, $orderItems, $paymentType);
        error_log("VERIFY-PAYMENT DEBUG: Email confirmation sent (Status: " . ($emailStatus ? 'SUCCESS' : 'FAILED') . ")");

        // Notifier l'admin
        error_log("VERIFY-PAYMENT DEBUG: Calling EmailService::sendNewOrderNotificationToAdmin");
        $adminEmailStatus = $emailService->sendNewOrderNotificationToAdmin($fullOrder, $customer, $orderItems);
        error_log("VERIFY-PAYMENT DEBUG: Admin notification sent (Status: " . ($adminEmailStatus ? 'SUCCESS' : 'FAILED') . ")");

        // Marquer le lien de paiement comme utilisé
        if ($paymentLinkToken) {
            require_once __DIR__ . '/../../models/PaymentLink.php';
            $paymentLinkModel = new PaymentLink();
            $paymentLinkModel->markAsUsed($paymentLinkToken);
            error_log("VERIFY-PAYMENT DEBUG: Payment link marked as used: {$paymentLinkToken}");
        }

        // Générer la facture PDF
        error_log("VERIFY-PAYMENT DEBUG: Calling InvoiceService::generateInvoice");
        try {
            $invoice = $invoiceService->generateInvoice($fullOrder, $customer, $orderItems);
            error_log("VERIFY-PAYMENT DEBUG: Invoice generated: {$invoice['filename']} for order ID: {$order['id']}");
        } catch (Exception $e) {
            error_log("VERIFY-PAYMENT ERROR: Failed to generate invoice for order ID: {$order['id']}: " . $e->getMessage());
        }

        // Créer une notification admin
        error_log("VERIFY-PAYMENT DEBUG: Creating admin notification...");
        require_once __DIR__ . '/../../models/AdminNotification.php';
        $notification = new AdminNotification();
        $notificationText = "Paiement confirmé pour la commande #{$order['id']}";
        if ($paymentType === 'deposit') $notificationText = "Acompte payé pour la commande #{$order['id']}";
        if ($paymentType === 'balance') $notificationText = "Solde payé pour la commande #{$order['id']}";

        $notification->create(
            'payment',
            $notificationText,
            $order['id']
        );
        error_log("VERIFY-PAYMENT DEBUG: Admin notification created");

        error_log("VERIFY-PAYMENT DEBUG: All processing steps completed");
    } else {
        error_log("VERIFY-PAYMENT DEBUG: Payment already processed by another process (Webhook or previous request). Skipping side effects.");
    }

    // Récupérer l'état final directement depuis la base de données
    $finalOrderQuery = "SELECT id, order_number, payment_status, deposit_payment_status, balance_payment_status, status FROM orders WHERE id = ?";
    $finalOrder = $db->queryOne($finalOrderQuery, [$order['id']]);

    if (!$finalOrder) {
        throw new Exception("Impossible de récupérer l'état final de la commande");
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status' => 'succeeded',
        'message' => 'Paiement confirmé et enregistré avec succès',
        'already_processed' => $alreadyProcessed,
        'order' => [
            'id' => $finalOrder['id'],
            'order_number' => $finalOrder['order_number'],
            'payment_status' => $finalOrder['payment_status'] ?? 'pending',
            'deposit_payment_status' => $finalOrder['deposit_payment_status'] ?? 'pending',
            'balance_payment_status' => $finalOrder['balance_payment_status'] ?? 'pending',
            'status' => $finalOrder['status']
        ]
    ]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("VERIFY-PAYMENT ERROR: Stripe API - " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur Stripe: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("VERIFY-PAYMENT ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
