<?php
/**
 * Script d'initialisation des tables clients et commandes
 * À exécuter une seule fois pour créer les tables nécessaires
 */

require_once __DIR__ . '/../core/Database.php';

try {
    $db = Database::getInstance()->getPDO();
    
    // Lire le fichier SQL
    $sql = file_get_contents(__DIR__ . '/customer_orders.sql');
    
    if ($sql === false) {
        throw new Exception("Impossible de lire le fichier customer_orders.sql");
    }
    
    // Exécuter le SQL
    $db->exec($sql);
    
    echo "✅ Tables créées avec succès !\n";
    echo "Tables créées :\n";
    echo "  - customers\n";
    echo "  - saved_configurations\n";
    echo "  - cart_items\n";
    echo "  - orders\n";
    echo "  - order_items\n";
    echo "  - admin_notifications\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la création des tables : " . $e->getMessage() . "\n";
    exit(1);
}
