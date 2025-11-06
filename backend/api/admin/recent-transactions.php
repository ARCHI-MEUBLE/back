<?php
/**
 * API Admin: Transactions récentes
 * GET /api/admin/recent-transactions?period=30d
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();
    $period = $_GET['period'] ?? '30d';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

    // Convertir la période en nombre de jours
    $days = match($period) {
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '1y' => 365,
        default => 30
    };

    $dateFrom = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

    // Récupérer les 20 transactions les plus récentes
    $query = "SELECT
        o.id,
        o.order_number,
        o.total_amount as amount,
        o.payment_method,
        o.payment_status,
        o.stripe_payment_intent_id,
        o.created_at,
        c.first_name || ' ' || c.last_name as customer_name,
        c.email as customer_email
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    WHERE o.created_at >= ?
    ORDER BY o.created_at DESC
    LIMIT ?";

    $transactions = $db->query($query, [$dateFrom, $limit]);

    // Formater les transactions
    $formattedTransactions = [];
    $methodsMap = [
        'card' => 'Carte bancaire',
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
        'bank_transfer' => 'Virement bancaire',
        'cash' => 'Espèces',
        'check' => 'Chèque'
    ];

    foreach ($transactions as $transaction) {
        $formattedTransactions[] = [
            'id' => (string) $transaction['id'],
            'order_number' => $transaction['order_number'],
            'customer_name' => $transaction['customer_name'],
            'customer_email' => $transaction['customer_email'],
            'amount' => (float) $transaction['amount'],
            'payment_method' => $methodsMap[$transaction['payment_method']] ?? ucfirst($transaction['payment_method']),
            'payment_status' => $transaction['payment_status'],
            'stripe_payment_intent_id' => $transaction['stripe_payment_intent_id'] ?? null,
            'created_at' => $transaction['created_at']
        ];
    }

    http_response_code(200);
    echo json_encode($formattedTransactions);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
