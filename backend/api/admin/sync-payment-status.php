<?php
/**
 * API Admin - Re-synchroniser le statut de paiement avec Stripe
 * POST /api/admin/sync-payment-status
 *
 * Utile en développement local quand les webhooks ne fonctionnent pas
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../core/Session.php';

try {
    // Vérifier l'authentification admin
    $session = Session::getInstance();
    if (!$session->has('admin_email') || $session->get('is_admin') !== true) {
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

    // Charger la librairie Stripe
    require_once __DIR__ . '/../../../vendor/stripe/init.php';

    // Récupérer la clé secrète Stripe depuis .env
    $stripeSecretKey = getenv('STRIPE_SECRET_KEY');

    if (!$stripeSecretKey || $stripeSecretKey === 'sk_test_YOUR_SECRET_KEY_HERE') {
        throw new Exception('Clé Stripe non configurée');
    }

    \Stripe\Stripe::setApiKey($stripeSecretKey);

    require_once __DIR__ . '/../../core/Database.php';
    $db = Database::getInstance();

    // Récupérer la commande
    $orderQuery = "SELECT * FROM orders WHERE id = ?";
    $order = $db->queryOne($orderQuery, [$orderId]);

    if (!$order) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Commande introuvable'
        ]);
        exit;
    }

    $updates = [];
    $logs = [];

    // Vérifier le paiement complet (si stripe_payment_intent_id existe)
    if ($order['stripe_payment_intent_id']) {
        try {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($order['stripe_payment_intent_id']);

            if ($paymentIntent->status === 'succeeded') {
                $updates[] = "payment_status = 'paid'";
                $updates[] = "status = 'confirmed'";
                if (!$order['confirmed_at']) {
                    $updates[] = "confirmed_at = CURRENT_TIMESTAMP";
                }
                $logs[] = "Paiement complet confirmé (PI: {$order['stripe_payment_intent_id']})";
            } elseif ($paymentIntent->status === 'canceled' || $paymentIntent->status === 'failed') {
                $updates[] = "payment_status = 'failed'";
                $logs[] = "Paiement complet échoué (PI: {$order['stripe_payment_intent_id']})";
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $logs[] = "Erreur lors de la vérification du paiement complet: " . $e->getMessage();
        }
    }

    // Vérifier l'acompte (si deposit_stripe_intent_id existe)
    if ($order['deposit_stripe_intent_id']) {
        try {
            $depositIntent = \Stripe\PaymentIntent::retrieve($order['deposit_stripe_intent_id']);

            if ($depositIntent->status === 'succeeded') {
                $updates[] = "deposit_payment_status = 'paid'";
                $updates[] = "payment_status = 'partially_paid'";
                $updates[] = "status = 'confirmed'";
                if (!$order['confirmed_at']) {
                    $updates[] = "confirmed_at = CURRENT_TIMESTAMP";
                }
                $logs[] = "Acompte confirmé (PI: {$order['deposit_stripe_intent_id']})";
            } elseif ($depositIntent->status === 'canceled' || $depositIntent->status === 'failed') {
                $updates[] = "deposit_payment_status = 'failed'";
                $logs[] = "Acompte échoué (PI: {$order['deposit_stripe_intent_id']})";
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $logs[] = "Erreur lors de la vérification de l'acompte: " . $e->getMessage();
        }
    }

    // Vérifier le solde (si balance_stripe_intent_id existe)
    if ($order['balance_stripe_intent_id']) {
        try {
            $balanceIntent = \Stripe\PaymentIntent::retrieve($order['balance_stripe_intent_id']);

            if ($balanceIntent->status === 'succeeded') {
                $updates[] = "balance_payment_status = 'paid'";
                $updates[] = "payment_status = 'paid'";
                $logs[] = "Solde confirmé (PI: {$order['balance_stripe_intent_id']})";
            } elseif ($balanceIntent->status === 'canceled' || $balanceIntent->status === 'failed') {
                $updates[] = "balance_payment_status = 'failed'";
                $logs[] = "Solde échoué (PI: {$order['balance_stripe_intent_id']})";
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $logs[] = "Erreur lors de la vérification du solde: " . $e->getMessage();
        }
    }

    // Appliquer les mises à jour si nécessaire
    if (!empty($updates)) {
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $updateQuery = "UPDATE orders SET " . implode(", ", array_unique($updates)) . " WHERE id = ?";
        $db->execute($updateQuery, [$orderId]);
        $logs[] = "Base de données mise à jour";
    } else {
        $logs[] = "Aucune mise à jour nécessaire";
    }

    // Récupérer l'état final
    $finalOrder = $db->queryOne($orderQuery, [$orderId]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Synchronisation effectuée avec succès',
        'logs' => $logs,
        'order' => [
            'id' => $finalOrder['id'],
            'order_number' => $finalOrder['order_number'],
            'payment_status' => $finalOrder['payment_status'],
            'deposit_payment_status' => $finalOrder['deposit_payment_status'],
            'balance_payment_status' => $finalOrder['balance_payment_status'],
            'status' => $finalOrder['status']
        ]
    ]);

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
