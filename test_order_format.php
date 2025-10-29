<?php
/**
 * Script de test : Vérifier le format des commandes pour le frontend
 */

require_once __DIR__ . '/backend/models/Order.php';

$order = new Order();

// Récupérer toutes les commandes
$ordersFile = __DIR__ . '/database/orders.json';
$content = file_get_contents($ordersFile);
$orders = json_decode($content, true);

echo "=== TEST DU FORMATAGE POUR LE FRONTEND ===\n\n";

foreach ($orders as $orderId => $orderData) {
    echo "Commande: {$orderData['order_number']}\n";
    echo "AVANT formatage:\n";
    echo "  - total: " . ($orderData['total'] ?? 'MANQUANT') . "\n";
    echo "  - amount: " . ($orderData['amount'] ?? 'MANQUANT') . "\n";

    if (!empty($orderData['items'])) {
        $firstItem = $orderData['items'][0];
        echo "  - Premier item - price: " . ($firstItem['price'] ?? 'MANQUANT') . "\n";
        echo "  - Premier item - configuration.price: " . ($firstItem['configuration']['price'] ?? 'MANQUANT') . "\n";
        echo "  - Premier item - prompt: " . ($firstItem['prompt'] ?? 'MANQUANT') . "\n";
        echo "  - Premier item - configuration.prompt: " . ($firstItem['configuration']['prompt'] ?? 'MANQUANT') . "\n";
    }

    // Formater
    $formatted = $order->formatForFrontend($orderData);

    echo "\nAPRES formatage:\n";
    echo "  - total: " . ($formatted['total'] ?? 'MANQUANT') . "\n";
    echo "  - amount: " . ($formatted['amount'] ?? 'MANQUANT') . " ✓ (ajouté)\n";

    if (!empty($formatted['items'])) {
        $firstItem = $formatted['items'][0];
        echo "  - Premier item - price: " . ($firstItem['price'] ?? 'MANQUANT') . " ✓ (ajouté)\n";
        echo "  - Premier item - prompt: " . ($firstItem['prompt'] ?? 'MANQUANT') . " ✓ (ajouté)\n";
        echo "  - Premier item - configuration.price: " . ($firstItem['configuration']['price'] ?? 'MANQUANT') . " (conservé)\n";
    }

    echo "\n" . str_repeat("-", 60) . "\n\n";
}

echo "✅ Test terminé !\n";
