<?php
/**
 * Script pour corriger la table models sur Railway
 * Ajoute les colonnes category et config_data
 * Usage: railway run php backend/database/migrations/apply_fix_models.php
 */

// Essayer de détecter le chemin de la base de données
$dbPath = getenv('DB_PATH');
if (!$dbPath) {
    if (file_exists('/data/archimeuble_dev.db')) {
        $dbPath = '/data/archimeuble_dev.db';
    } elseif (file_exists('/data/archimeuble.db')) {
        $dbPath = '/data/archimeuble.db';
    } else {
        $dbPath = dirname(__DIR__, 2) . '/database/archimeuble.db';
    }
}

echo "🚀 Correction de la table models...\n";
echo "📂 Base de données: $dbPath\n";

if (!file_exists($dbPath)) {
    die("❌ Erreur: Base de données non trouvée à $dbPath\n");
}

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "🔄 Vérification des colonnes...\n";

    // Vérifier si category existe
    $columns = $db->query("PRAGMA table_info(models)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');

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
