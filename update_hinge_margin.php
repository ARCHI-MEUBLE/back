<?php
/**
 * Script de mise à jour de la marge des charnières
 * Change la valeur par défaut de 20mm à 150mm (15cm)
 */

try {
    // Connexion directe à SQLite
    $dbPath = __DIR__ . '/database/archimeuble.db';
    
    if (!file_exists($dbPath)) {
        throw new Exception("Base de données introuvable : $dbPath");
    }
    
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔧 Mise à jour de la marge des charnières...\n\n";
    
    // Vérifier si le paramètre existe
    $stmt = $db->prepare("SELECT * FROM facade_settings WHERE setting_key = 'hinge_edge_margin'");
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Mettre à jour la valeur existante
        $stmt = $db->prepare("
            UPDATE facade_settings 
            SET setting_value = '150',
                updated_at = CURRENT_TIMESTAMP
            WHERE setting_key = 'hinge_edge_margin'
        ");
        $stmt->execute();
        
        echo "✅ Marge des charnières mise à jour : {$existing['setting_value']}mm → 150mm (15cm)\n";
    } else {
        // Créer le paramètre s'il n'existe pas
        $stmt = $db->prepare("
            INSERT INTO facade_settings (setting_key, setting_value, created_at, updated_at)
            VALUES ('hinge_edge_margin', '150', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute();
        
        echo "✅ Paramètre 'hinge_edge_margin' créé avec la valeur 150mm (15cm)\n";
    }
    
    // Afficher la nouvelle valeur
    $stmt = $db->prepare("SELECT * FROM facade_settings WHERE setting_key = 'hinge_edge_margin'");
    $stmt->execute();
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n📊 Valeur actuelle dans la base de données :\n";
    echo "   Clé : {$updated['setting_key']}\n";
    echo "   Valeur : {$updated['setting_value']}mm\n";
    echo "   Mis à jour : {$updated['updated_at']}\n";
    
    echo "\n✨ Mise à jour terminée avec succès !\n";
    echo "   Les charnières seront maintenant positionnées à 15cm des bords.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la mise à jour : " . $e->getMessage() . "\n";
    exit(1);
}
