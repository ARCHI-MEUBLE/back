<?php
/**
 * API Publique - Créer un PaymentIntent via lien de paiement
 * POST /api/payment-link/create-payment-intent
 *
 * Accessible publiquement (pas d'authentification requise)
 * Sécurisé par le token unique du lien de paiement
 */

// Charger les variables d'environnement depuis .env (DOIT être en premier)
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

require_once __DIR__ . '/../../models/PaymentLink.php';

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

    if (!isset($data['token'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Token manquant'
        ]);
        exit;
    }

    $token = trim($data['token']);
    $installments = isset($data['installments']) ? intval($data['installments']) : 1;

    // Valider le nombre d'acomptes
    if (!in_array($installments, [1, 3])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Nombre d\'acomptes invalide (1 ou 3 uniquement)'
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
            'error' => $validation['message']
        ]);
        exit;
    }

    // Récupérer les détails de la commande
    $orderData = $paymentLink->getOrderItemsByToken($token);

    if (!$orderData) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Commande introuvable'
        ]);
        exit;
    }

    $order = $orderData['order'];
    $paymentLinkAmount = floatval($order['amount']);
    $paymentType = $order['payment_type'] ?? 'full';

    error_log("CREATE-PI: Token {$token}, Order ID {$order['order_id']}, Type: {$paymentType}, Amount: {$paymentLinkAmount}");

    // Charger la librairie Stripe
    error_log("CREATE-PI: Loading Stripe library...");
    require_once __DIR__ . '/../../../vendor/stripe/init.php';
    error_log("CREATE-PI: Stripe library loaded");

    // Récupérer la clé secrète Stripe depuis .env
    $stripeSecretKey = getenv('STRIPE_SECRET_KEY');
    error_log("CREATE-PI: Stripe key loaded: " . (empty($stripeSecretKey) ? 'NO' : 'YES'));

    if (!$stripeSecretKey || $stripeSecretKey === 'sk_test_YOUR_SECRET_KEY_HERE') {
        throw new Exception('Clé Stripe non configurée');
    }

    \Stripe\Stripe::setApiKey($stripeSecretKey);
    error_log("CREATE-PI: Stripe API key set");

    // Convertir le montant en centimes
    $amountInCents = (int)($paymentLinkAmount * 100);
    error_log("CREATE-PI: Amount in cents: {$amountInCents}");

    // Créer ou récupérer le customer Stripe
    require_once __DIR__ . '/../../core/Database.php';
    $db = Database::getInstance();

    // Récupérer le customer_id depuis la commande
    $customerQuery = "SELECT c.*, o.customer_id
                      FROM orders o
                      JOIN customers c ON o.customer_id = c.id
                      WHERE o.id = ?";
    $customerResult = $db->query($customerQuery, [$order['order_id']]);
    error_log("CREATE-PI: Customer query executed, found: " . count($customerResult) . " results");

    if (empty($customerResult)) {
        throw new Exception('Client introuvable');
    }

    $customer = $customerResult[0];
    error_log("CREATE-PI: Customer loaded: ID={$customer['customer_id']}, Email={$customer['email']}");

    // Créer ou récupérer le customer Stripe
    $stripeCustomerId = null;
    if (isset($customer['stripe_customer_id']) && $customer['stripe_customer_id']) {
        $stripeCustomerId = $customer['stripe_customer_id'];
        error_log("CREATE-PI: Using existing Stripe customer: {$stripeCustomerId}");
    } else {
        error_log("CREATE-PI: Creating new Stripe customer...");
        // Créer un nouveau customer Stripe
        $stripeCustomer = \Stripe\Customer::create([
            'email' => $customer['email'],
            'name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
            'phone' => $customer['phone'] ?? null,
            'metadata' => [
                'customer_id' => $customer['customer_id'],
                'order_id' => $order['order_id'],
                'payment_link_token' => $token
            ]
        ]);
        $stripeCustomerId = $stripeCustomer->id;
        error_log("CREATE-PI: Stripe customer created: {$stripeCustomerId}");

        // Sauvegarder le Stripe customer ID
        $updateQuery = "UPDATE customers SET stripe_customer_id = ? WHERE id = ?";
        $db->execute($updateQuery, [$stripeCustomerId, $customer['customer_id']]);
        error_log("CREATE-PI: Stripe customer ID saved to database");
    }

    // Préparer les paramètres du PaymentIntent
    $paymentIntentParams = [
        'amount' => $amountInCents,
        'currency' => 'eur',
        'customer' => $stripeCustomerId,
        'automatic_payment_methods' => [
            'enabled' => true,
        ],
        'metadata' => [
            'order_id' => $order['order_id'],
            'order_number' => $order['order_number'],
            'customer_id' => $customer['customer_id'],
            'payment_link_token' => $token,
            'installments' => $installments,
            'payment_type' => $paymentType,
            'source' => 'payment_link'
        ]
    ];

    // Si paiement en 3 fois
    if ($installments === 3) {
        $firstPaymentAmount = (int)ceil($amountInCents / 3);
        $paymentIntentParams['amount'] = $firstPaymentAmount;
        $paymentIntentParams['metadata']['installment_number'] = 1;
        $paymentIntentParams['metadata']['total_amount'] = $amountInCents;
        $paymentIntentParams['description'] = 'ArchiMeuble - Commande ' . $order['order_number'] . ' - Paiement 1/3 (' . $paymentType . ')';
    } else {
        $paymentIntentParams['description'] = 'ArchiMeuble - Commande ' . $order['order_number'] . ' - Paiement ' . $paymentType;
    }

    // Créer le PaymentIntent
    error_log("CREATE-PI: Creating Stripe PaymentIntent with amount {$amountInCents}...");
    $paymentIntent = \Stripe\PaymentIntent::create($paymentIntentParams);
    error_log("CREATE-PI: PaymentIntent created: {$paymentIntent->id}, Status: {$paymentIntent->status}");

    // Enregistrer le PaymentIntent dans la base de données
    error_log("CREATE-PI: Saving PaymentIntent to database...");
    $insertQuery = "INSERT INTO stripe_payment_intents
                    (payment_intent_id, order_id, customer_id, amount, currency, status, metadata, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

    $db->execute($insertQuery, [
        $paymentIntent->id,
        $order['order_id'],
        $customer['customer_id'],
        $paymentIntent->amount,
        $paymentIntent->currency,
        $paymentIntent->status,
        json_encode($paymentIntent->metadata->toArray())
    ]);
    error_log("CREATE-PI: PaymentIntent saved to database");

    // Mettre à jour l'ID d'intention dans la commande pour faciliter le suivi par le webhook
    error_log("CREATE-PI: Updating order with PaymentIntent ID (type: {$paymentType})...");
    if ($paymentType === 'deposit') {
        $db->execute("UPDATE orders SET deposit_stripe_intent_id = ? WHERE id = ?", [$paymentIntent->id, $order['order_id']]);
    } elseif ($paymentType === 'balance') {
        $db->execute("UPDATE orders SET balance_stripe_intent_id = ? WHERE id = ?", [$paymentIntent->id, $order['order_id']]);
    } else {
        $db->execute("UPDATE orders SET stripe_payment_intent_id = ? WHERE id = ?", [$paymentIntent->id, $order['order_id']]);
    }
    error_log("CREATE-PI: Order updated successfully");

    // Retourner la réponse
    error_log("CREATE-PI: Sending success response");
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'clientSecret' => $paymentIntent->client_secret,
            'paymentIntentId' => $paymentIntent->id,
            'amount' => $paymentIntent->amount,
            'installments' => $installments,
            'order_number' => $order['order_number']
        ]
    ]);
    error_log("CREATE-PI: Success response sent");

} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur Stripe: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
