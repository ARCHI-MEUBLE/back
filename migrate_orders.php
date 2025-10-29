<?php
/**
 * Script de migration: Ajouter les infos client aux commandes existantes
 */

require_once __DIR__ . '/backend/core/Database.php';

$ordersFile = __DIR__ . '/database/orders.json';

if (!file_exists($ordersFile)) {
    echo "Aucun fichier orders.json trouvé.\n";
    exit(0);
}

$content = file_get_contents($ordersFile);
$orders = json_decode($content, true);

if (!$orders) {
    echo "Aucune commande à migrer.\n";
    exit(0);
}

$db = Database::getInstance();
$updated = 0;

foreach ($orders as $orderId => &$order) {
    // Si la commande n'a pas déjà les infos client
    if (!isset($order['customer']) && isset($order['customer_id'])) {
        $customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$order['customer_id']]);
        if ($customer) {
            $order['customer'] = $customer;
            $updated++;
            echo "✓ Commande {$order['order_number']} enrichie avec les infos de {$customer['email']}\n";
        }
    }
}

if ($updated > 0) {
    file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT));
    echo "\n✅ Migration terminée: {$updated} commande(s) mise(s) à jour.\n";
} else {
    echo "Aucune commande à mettre à jour.\n";
}
