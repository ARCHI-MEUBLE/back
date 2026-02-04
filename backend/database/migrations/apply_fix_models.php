<?php
/**
 * Script pour corriger la table models sur Railway
 * Ajoute les colonnes category et config_data
 * Usage: railway run php backend/database/migrations/apply_fix_models.php
 */

require_once __DIR__ . '/../../core/Database.php';

echo "🚀 Correction de la table models...\n";

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getPDO();

    echo "🔄 Vérification des colonnes...\n";

    // Vérifier si category existe
    $stmt = $db->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = :table AND table_schema = 'public'");
    $stmt->execute(['table' => 'models']);
    $columnNames = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!in_array('category', $columnNames)) {
        echo "➕ Ajout de la colonne 'category'...\n";
        $db->exec("ALTER TABLE models ADD COLUMN category TEXT");
        echo "✅ Colonne 'category' ajoutée.\n";
    } else {
        echo "ℹ️ La colonne 'category' existe déjà.\n";
    }

    if (!in_array('config_data', $columnNames)) {
        echo "➕ Ajout de la colonne 'config_data'...\n";
        $db->exec("ALTER TABLE models ADD COLUMN config_data TEXT");
        echo "✅ Colonne 'config_data' ajoutée.\n";
    } else {
        echo "ℹ️ La colonne 'config_data' existe déjà.\n";
    }

    echo "\n✨ Correction terminée avec succès!\n";

} catch (PDOException $e) {
    die("❌ Erreur PDO: " . $e->getMessage() . "\n");
}
