<?php
/**
 * API Stripe: Créer un PaymentIntent
 * POST /api/stripe/create-payment-intent
 *
 * Crée un PaymentIntent Stripe pour traiter un paiement
 * Supporte le paiement en 1 fois ou 3 fois
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

// Charger la librairie Stripe
require_once __DIR__ . '/../../../vendor/stripe/init.php';

try {
    // Récupérer la clé secrète Stripe depuis .env
    $stripeSecretKey = getenv('STRIPE_SECRET_KEY');

    if (!$stripeSecretKey || $stripeSecretKey === 'sk_test_YOUR_SECRET_KEY_HERE') {
        throw new Exception('Clé Stripe non configurée. Veuillez configurer STRIPE_SECRET_KEY dans le fichier .env');
    }

    \Stripe\Stripe::setApiKey($stripeSecretKey);

    $data = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (!isset($data['amount']) || $data['amount'] <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Montant invalide']);
        exit;
    }

    $amount = (int)($data['amount'] * 100); // Convertir en centimes
    $installments = isset($data['installments']) ? (int)$data['installments'] : 1;
    $currency = $data['currency'] ?? 'eur';
    $paymentType = $data['payment_type'] ?? 'full'; // 'full', 'deposit', 'balance'

    // Récupérer les infos du client
    require_once __DIR__ . '/../../models/Customer.php';
    $customerModel = new Customer();
    $customer = $customerModel->getById($_SESSION['customer_id']);

    // Créer ou récupérer le customer Stripe
    $stripeCustomerId = null;
    if (isset($customer['stripe_customer_id']) && $customer['stripe_customer_id']) {
        $stripeCustomerId = $customer['stripe_customer_id'];
    } else {
        // Créer un nouveau customer Stripe
        $stripeCustomer = \Stripe\Customer::create([
            'email' => $customer['email'],
            'name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
            'metadata' => [
                'customer_id' => $_SESSION['customer_id']
            ]
        ]);
        $stripeCustomerId = $stripeCustomer->id;

        // Sauvegarder le Stripe customer ID
        $customerModel->updateStripeCustomerId($_SESSION['customer_id'], $stripeCustomerId);
    }

    // Préparer les paramètres du PaymentIntent
    $paymentIntentParams = [
        'amount' => $amount,
        'currency' => $currency,
        'customer' => $stripeCustomerId,
        'automatic_payment_methods' => [
            'enabled' => true,
        ],
        'metadata' => [
            'customer_id' => $_SESSION['customer_id'],
            'installments' => $installments,
            'payment_type' => $paymentType
        ]
    ];

    // Si paiement en 3 fois, configurer les paramètres spécifiques
    if ($installments === 3) {
        // ... (code existant)
    } else {
        if ($paymentType === 'deposit') {
            $paymentIntentParams['description'] = 'ArchiMeuble - Acompte de commande';
        } elseif ($paymentType === 'balance') {
            $paymentIntentParams['description'] = 'ArchiMeuble - Solde de commande';
        } else {
            $paymentIntentParams['description'] = 'ArchiMeuble - Paiement intégral';
        }
    }

    // Créer le PaymentIntent
    $paymentIntent = \Stripe\PaymentIntent::create($paymentIntentParams);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'clientSecret' => $paymentIntent->client_secret,
        'paymentIntentId' => $paymentIntent->id,
        'amount' => $paymentIntent->amount,
        'installments' => $installments
    ]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Erreur Stripe: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
