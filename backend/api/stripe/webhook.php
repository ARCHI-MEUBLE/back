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
            $metadata = $paymentIntent->metadata ? $paymentIntent->metadata->toArray() : [];
            $paymentType = $metadata['payment_type'] ?? 'full';
            $paymentLinkToken = $metadata['payment_link_token'] ?? null;
            $orderId = $metadata['order_id'] ?? null;

            error_log("WEBHOOK: Payment succeeded for intent {$paymentIntent->id}. Metadata: " . json_encode($metadata));

            // Find associated order
            $order = null;
            if ($orderId) {
                // If order_id is in metadata (payment link case)
                $query = "SELECT * FROM orders WHERE id = ?";
                $order = $db->queryOne($query, [$orderId]);
                if ($order) {
                    error_log("WEBHOOK: Found order #{$order['order_number']} (ID: {$order['id']}) via metadata order_id");
                }
            } 
            
            if (!$order) {
                $query = "SELECT * FROM orders WHERE deposit_stripe_intent_id = ?";
                $order = $db->queryOne($query, [$paymentIntent->id]);
                if ($order) {
                    $paymentType = 'deposit'; // Force type if found here
                    error_log("WEBHOOK: Found order #{$order['order_number']} (ID: {$order['id']}) via deposit_stripe_intent_id. Forcing type: deposit");
                }
            } 
            
            if (!$order) {
                $query = "SELECT * FROM orders WHERE balance_stripe_intent_id = ?";
                $order = $db->queryOne($query, [$paymentIntent->id]);
                if ($order) {
                    $paymentType = 'balance'; // Force type if found here
                    error_log("WEBHOOK: Found order #{$order['order_number']} (ID: {$order['id']}) via balance_stripe_intent_id. Forcing type: balance");
                }
            } 
            
            if (!$order) {
                // Default: look via stripe_payment_intent_id
                $query = "SELECT * FROM orders WHERE stripe_payment_intent_id = ?";
                $order = $db->queryOne($query, [$paymentIntent->id]);
                if ($order) {
                    $paymentType = 'full'; // Force type if found here
                    error_log("WEBHOOK: Found order #{$order['order_number']} (ID: {$order['id']}) via stripe_payment_intent_id. Forcing type: full");
                }
            }

            if ($order) {
                // Determine payment type more reliably if it's currently 'full'
                if ($paymentType === 'full') {
                    if ($paymentIntent->id === ($order['deposit_stripe_intent_id'] ?? '')) {
                        $paymentType = 'deposit';
                    } elseif ($paymentIntent->id === ($order['balance_stripe_intent_id'] ?? '')) {
                        $paymentType = 'balance';
                    }
                }

                // Check if already processed to avoid double emails/invoices
                $alreadyProcessed = false;
                if ($paymentType === 'deposit' && ($order['deposit_payment_status'] ?? 'pending') === 'paid') {
                    $alreadyProcessed = true;
                } elseif ($paymentType === 'balance' && ($order['balance_payment_status'] ?? 'pending') === 'paid') {
                    $alreadyProcessed = true;
                } elseif ($paymentType === 'full' && $order['payment_status'] === 'paid') {
                    $alreadyProcessed = true;
                }

                if ($alreadyProcessed) {
                    error_log("WEBHOOK: Payment for order #{$order['id']} already processed ($paymentType). Skipping notifications.");
                    http_response_code(200);
                    echo json_encode(['success' => true, 'message' => 'Already processed']);
                    exit;
                }

                // Update payment status based on type
                if ($paymentType === 'deposit') {
                    error_log("WEBHOOK: Updating order #{$order['id']} as partially_paid (deposit paid)");
                    $updateQuery = "UPDATE orders
                                   SET deposit_payment_status = 'paid',
                                       payment_status = 'partially_paid',
                                       status = 'confirmed',
                                       deposit_stripe_intent_id = ?,
                                       confirmed_at = CURRENT_TIMESTAMP,
                                       updated_at = CURRENT_TIMESTAMP
                                   WHERE id = ?";
                    $db->execute($updateQuery, [$paymentIntent->id, $order['id']]);
                } elseif ($paymentType === 'balance') {
                    error_log("WEBHOOK: Updating order #{$order['id']} as fully paid (balance paid)");
                    $updateQuery = "UPDATE orders
                                   SET balance_payment_status = 'paid',
                                       payment_status = 'paid',
                                       balance_stripe_intent_id = ?,
                                       updated_at = CURRENT_TIMESTAMP
                                   WHERE id = ?";
                    $db->execute($updateQuery, [$paymentIntent->id, $order['id']]);
                } else {
                    error_log("WEBHOOK: Updating order #{$order['id']} as fully paid (full payment)");
                    $updateQuery = "UPDATE orders
                                   SET payment_status = 'paid',
                                       status = 'confirmed',
                                       stripe_payment_intent_id = ?,
                                       confirmed_at = CURRENT_TIMESTAMP,
                                       updated_at = CURRENT_TIMESTAMP
                                   WHERE id = ?";
                    $db->execute($updateQuery, [$paymentIntent->id, $order['id']]);
                }

                // Clear customer's cart only on initial payment (full or deposit)
                if ($paymentType === 'full' || $paymentType === 'deposit') {
                    $cart->clear($order['customer_id']);
                }

                // Get fresh details for the email
                $customer = $customerModel->getById($order['customer_id']);
                $orderItems = $orderModel->getOrderItems($order['id']);
                $fullOrder = $orderModel->getById($order['id']);
                
                error_log("WEBHOOK: Sending confirmation for order #{$fullOrder['order_number']}. Type: {$paymentType}, Deposit Status: {$fullOrder['deposit_payment_status']}");

                // Send confirmation email to customer
                $emailService->sendOrderConfirmation($fullOrder, $customer, $orderItems, $paymentType);

                // Notify admin
                $emailService->sendNewOrderNotificationToAdmin($fullOrder, $customer, $orderItems);

                // If payment comes from a link, mark it as used
                if ($paymentLinkToken) {
                    require_once __DIR__ . '/../../models/PaymentLink.php';
                    $paymentLinkModel = new PaymentLink();
                    $paymentLinkModel->markAsUsed($paymentLinkToken);
                    error_log("Payment link marked as used: {$paymentLinkToken}");
                }

                // Generate PDF Invoice
                try {
                    $invoice = $invoiceService->generateInvoice($fullOrder, $customer, $orderItems);
                    error_log("Invoice generated: {$invoice['filename']} for order ID: {$order['id']}");
                } catch (Exception $e) {
                    error_log("Failed to generate invoice for order ID: {$order['id']}: " . $e->getMessage());
                }

                // Handle 3x installments if applicable
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

                // Admin dashboard notification
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

                error_log("Payment succeeded ($paymentType) for order ID: {$order['id']}");
            }
            break;

        case 'payment_intent.payment_failed':
            $paymentIntent = $event->data->object;
            $paymentType = $paymentIntent->metadata->payment_type ?? 'full';
            $orderId = $paymentIntent->metadata->order_id ?? null;

            // Find associated order
            $order = null;
            if ($orderId) {
                // If order_id is in metadata
                $query = "SELECT id, customer_id FROM orders WHERE id = ?";
                $order = $db->queryOne($query, [$orderId]);
            } elseif ($paymentType === 'deposit') {
                $query = "SELECT id, customer_id FROM orders WHERE deposit_stripe_intent_id = ?";
                $order = $db->queryOne($query, [$paymentIntent->id]);
            } elseif ($paymentType === 'balance') {
                $query = "SELECT id, customer_id FROM orders WHERE balance_stripe_intent_id = ?";
                $order = $db->queryOne($query, [$paymentIntent->id]);
            } else {
                $query = "SELECT id, customer_id FROM orders WHERE stripe_payment_intent_id = ?";
                $order = $db->queryOne($query, [$paymentIntent->id]);
            }

            if ($order) {
                // Update payment status
                if ($paymentType === 'deposit') {
                    $updateQuery = "UPDATE orders SET deposit_payment_status = 'failed', deposit_stripe_intent_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $db->execute($updateQuery, [$paymentIntent->id, $order['id']]);
                } elseif ($paymentType === 'balance') {
                    $updateQuery = "UPDATE orders SET balance_payment_status = 'failed', balance_stripe_intent_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $db->execute($updateQuery, [$paymentIntent->id, $order['id']]);
                } else {
                    $updateQuery = "UPDATE orders SET payment_status = 'failed', stripe_payment_intent_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $db->execute($updateQuery, [$paymentIntent->id, $order['id']]);
                }

                // Get details for email
                $fullOrder = $orderModel->getById($order['id']);
                $customer = $customerModel->getById($order['customer_id']);

                // Send failure email
                $emailService->sendPaymentFailedEmail($fullOrder, $customer);

                // Admin notification
                require_once __DIR__ . '/../../models/AdminNotification.php';
                $notification = new AdminNotification();
                $notification->create(
                    'payment',
                    "Échec du paiement ($paymentType) pour la commande #{$order['id']}",
                    $order['id']
                );

                error_log("Payment failed ($paymentType) for order ID: {$order['id']}");
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
