<?php
/**
 * API Stripe: Webhook pour recevoir les événements
 * POST /api/stripe/webhook
 *
 * Reçoit les notifications de Stripe sur les paiements
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Charger la librairie Stripe
require_once __DIR__ . '/../../../vendor/stripe/init.php';

try {
    // Récupérer les clés depuis .env
    $stripeSecretKey = getenv('STRIPE_SECRET_KEY');
    $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');

    if (!$stripeSecretKey || $stripeSecretKey === 'sk_test_YOUR_SECRET_KEY_HERE') {
        throw new Exception('Clé Stripe non configurée');
    }

    \Stripe\Stripe::setApiKey($stripeSecretKey);

    // Récupérer le payload et la signature
    $payload = @file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    // Vérifier la signature du webhook
    try {
        if ($webhookSecret && $webhookSecret !== 'whsec_YOUR_WEBHOOK_SECRET_HERE' && $webhookSecret !== 'whsec_test_local_dev') {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret
            );
        } else {
            // En dev local sans Stripe CLI, parser directement (NON sécurisé, uniquement pour dev)
            $event = json_decode($payload, false);
        }
    } catch (\UnexpectedValueException $e) {
        http_response_code(400);
        exit;
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        http_response_code(400);
        exit;
    }

    require_once __DIR__ . '/../../models/Order.php';
    require_once __DIR__ . '/../../models/Cart.php';
    require_once __DIR__ . '/../../models/Customer.php';
    require_once __DIR__ . '/../../core/Database.php';
    require_once __DIR__ . '/../../services/EmailService.php';
    require_once __DIR__ . '/../../services/InvoiceService.php';
    require_once __DIR__ . '/../../services/InstallmentService.php';

    $db = Database::getInstance();
    $orderModel = new Order();
    $cart = new Cart();
    $customerModel = new Customer();
    $emailService = new EmailService();
    $invoiceService = new InvoiceService();
    $installmentService = new InstallmentService();

    // Traiter l'événement selon son type
    switch ($event->type) {
        case 'payment_intent.succeeded':
            $paymentIntent = $event->data->object;

            // Trouver la commande associée via le stripe_payment_intent_id
            $query = "SELECT id, customer_id FROM orders WHERE stripe_payment_intent_id = ?";
            $order = $db->queryOne($query, [$paymentIntent->id]);

            if ($order) {
                // Mettre à jour le statut de paiement
                $updateQuery = "UPDATE orders
                               SET payment_status = 'paid',
                                   status = 'confirmed',
                                   confirmed_at = CURRENT_TIMESTAMP,
                                   updated_at = CURRENT_TIMESTAMP
                               WHERE id = ?";
                $db->execute($updateQuery, [$order['id']]);

                // Vider le panier du client
                $cart->clear($order['customer_id']);

                // Récupérer les détails complets de la commande
                $fullOrder = $orderModel->getById($order['id']);
                $customer = $customerModel->getById($order['customer_id']);
                $orderItems = $orderModel->getOrderItems($order['id']);

                // Envoyer email de confirmation au client
                $emailService->sendOrderConfirmation($fullOrder, $customer, $orderItems);

                // Envoyer notification à l'admin
                $emailService->sendNewOrderNotificationToAdmin($fullOrder, $customer, $orderItems);

                // Générer la facture PDF automatiquement
                try {
                    $invoice = $invoiceService->generateInvoice($fullOrder, $customer, $orderItems);
                    error_log("Invoice generated: {$invoice['filename']} for order ID: {$order['id']}");
                } catch (Exception $e) {
                    error_log("Failed to generate invoice for order ID: {$order['id']}: " . $e->getMessage());
                }

                // Créer les mensualités si paiement en 3 fois
                $installments = $paymentIntent->metadata->installments ?? 1;
                if ($installments == 3) {
                    try {
                        $installmentService->createInstallments(
                            $order['id'],
                            $order['customer_id'],
                            $fullOrder['total_amount']
                        );
                        error_log("Installments created for order ID: {$order['id']}");
                    } catch (Exception $e) {
                        error_log("Failed to create installments for order ID: {$order['id']}: " . $e->getMessage());
                    }
                }

                // Créer une notification dans le dashboard admin
                require_once __DIR__ . '/../../models/AdminNotification.php';
                $notification = new AdminNotification();
                $notification->create(
                    'payment',
                    "Paiement confirmé pour la commande #{$order['id']}",
                    $order['id']
                );

                // Logger pour debug
                error_log("Payment succeeded for order ID: {$order['id']}, cart cleared for customer ID: {$order['customer_id']}");
            }
            break;

        case 'payment_intent.payment_failed':
            $paymentIntent = $event->data->object;

            // Trouver la commande associée
            $query = "SELECT id, customer_id FROM orders WHERE stripe_payment_intent_id = ?";
            $order = $db->queryOne($query, [$paymentIntent->id]);

            if ($order) {
                // Mettre à jour le statut de paiement
                $updateQuery = "UPDATE orders
                               SET payment_status = 'failed',
                                   updated_at = CURRENT_TIMESTAMP
                               WHERE id = ?";
                $db->execute($updateQuery, [$order['id']]);

                // Récupérer les détails pour l'email
                $fullOrder = $orderModel->getById($order['id']);
                $customer = $customerModel->getById($order['customer_id']);

                // Envoyer email d'échec au client
                $emailService->sendPaymentFailedEmail($fullOrder, $customer);

                // Créer une notification pour l'admin
                require_once __DIR__ . '/../../models/AdminNotification.php';
                $notification = new AdminNotification();
                $notification->create(
                    'payment',
                    "Échec du paiement pour la commande #{$order['id']}",
                    $order['id']
                );

                error_log("Payment failed for order ID: {$order['id']}");
            }
            break;

        case 'charge.refunded':
            $charge = $event->data->object;
            $paymentIntentId = $charge->payment_intent;

            // Trouver la commande
            $query = "SELECT id FROM orders WHERE stripe_payment_intent_id = ?";
            $order = $db->queryOne($query, [$paymentIntentId]);

            if ($order) {
                // Mettre à jour le statut
                $updateQuery = "UPDATE orders
                               SET payment_status = 'refunded',
                                   status = 'cancelled',
                                   updated_at = CURRENT_TIMESTAMP
                               WHERE id = ?";
                $db->execute($updateQuery, [$order['id']]);

                error_log("Payment refunded for order ID: {$order['id']}");
            }
            break;

        default:
            // Événement non géré
            error_log("Unhandled webhook event type: {$event->type}");
    }

    http_response_code(200);
    echo json_encode(['received' => true]);

} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
}
