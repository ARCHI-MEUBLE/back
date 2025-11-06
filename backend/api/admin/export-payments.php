<?php
/**
 * API Admin: Export CSV des paiements
 * GET /api/admin/export-payments?period=30d
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

    // Convertir la période en nombre de jours
    $days = match($period) {
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '1y' => 365,
        default => 30
    };

    $dateFrom = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

    // Récupérer toutes les transactions pour la période
    $query = "SELECT
        o.id,
        o.order_number,
        o.total_amount,
        o.payment_method,
        o.payment_status,
        o.stripe_payment_intent_id,
        o.created_at,
        o.updated_at,
        c.first_name,
        c.last_name,
        c.email,
        c.phone
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    WHERE o.created_at >= ?
    ORDER BY o.created_at DESC";

    $transactions = $db->query($query, [$dateFrom]);

    // Formater les méthodes de paiement
    $methodsMap = [
        'card' => 'Carte bancaire',
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
        'bank_transfer' => 'Virement bancaire',
        'cash' => 'Espèces',
        'check' => 'Chèque'
    ];

    // Formater les statuts
    $statusMap = [
        'pending' => 'En attente',
        'paid' => 'Payé',
        'failed' => 'Échoué',
        'refunded' => 'Remboursé'
    ];

    // Créer le CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="paiements_' . $period . '_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Ajouter le BOM UTF-8 pour Excel
    echo "\xEF\xBB\xBF";

    // Ouvrir le flux de sortie
    $output = fopen('php://output', 'w');

    // En-têtes du CSV
    fputcsv($output, [
        'ID',
        'N° Commande',
        'Prénom',
        'Nom',
        'Email',
        'Téléphone',
        'Montant',
        'Méthode de paiement',
        'Statut',
        'Stripe Payment Intent',
        'Date création',
        'Date mise à jour'
    ], ';');

    // Données
    foreach ($transactions as $transaction) {
        fputcsv($output, [
            $transaction['id'],
            $transaction['order_number'],
            $transaction['first_name'],
            $transaction['last_name'],
            $transaction['email'],
            $transaction['phone'] ?? '',
            number_format($transaction['total_amount'], 2, ',', ' ') . ' €',
            $methodsMap[$transaction['payment_method']] ?? ucfirst($transaction['payment_method']),
            $statusMap[$transaction['payment_status']] ?? ucfirst($transaction['payment_status']),
            $transaction['stripe_payment_intent_id'] ?? '',
            date('d/m/Y H:i', strtotime($transaction['created_at'])),
            date('d/m/Y H:i', strtotime($transaction['updated_at'] ?? $transaction['created_at']))
        ], ';');
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
