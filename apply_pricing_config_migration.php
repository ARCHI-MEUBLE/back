<?php
/**
 * Script pour appliquer la migration pricing_config
 */

$dbPath = __DIR__ . '/archimeuble.db';
$migrationPath = __DIR__ . '/backend/migrations/010_create_pricing_config.sql';

echo "🚀 Application de la migration pricing_config...\n";
echo "📂 Base de données: $dbPath\n";
echo "📄 Fichier migration: $migrationPath\n\n";

// Vérifier que la base existe
if (!file_exists($dbPath)) {
    die("❌ Erreur: Base de données non trouvée à $dbPath\n");
}

// Vérifier que le fichier migration existe
if (!file_exists($migrationPath)) {
    die("❌ Erreur: Fichier migration non trouvé à $migrationPath\n");
}

// Lire le fichier SQL
$sql = file_get_contents($migrationPath);
if ($sql === false) {
    die("❌ Erreur: Impossible de lire le fichier migration\n");
}

try {
    // Connexion à la base
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "🔄 Connexion à la base établie\n";

    // Exécuter la migration
    $db->exec($sql);

    echo "✅ Migration appliquée avec succès!\n\n";

    // Vérifier la table créée
    echo "📊 Vérification de la table créée:\n";
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name = 'pricing_config'");
    $table = $stmt->fetchColumn();

    if ($table) {
        echo "  ✓ $table\n";
    }

    // Compter les paramètres insérés
    echo "\n📈 Statistiques:\n";
    $totalParams = $db->query("SELECT COUNT(*) FROM pricing_config")->fetchColumn();
    echo "  - Total paramètres: $totalParams\n";

    // Compter par catégorie
    $stmt = $db->query("SELECT category, COUNT(*) as count FROM pricing_config GROUP BY category ORDER BY category");
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n📋 Paramètres par catégorie:\n";
    foreach ($counts as $row) {
        echo "  - {$row['category']}: {$row['count']} paramètre(s)\n";
    }

    // Afficher quelques exemples
    echo "\n🔍 Exemples de paramètres:\n";
    $stmt = $db->query("
        SELECT category, item_type, param_name, param_value, unit
        FROM pricing_config
        LIMIT 10
    ");
    $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($examples as $ex) {
        echo "  - {$ex['category']}/{$ex['item_type']}/{$ex['param_name']}: {$ex['param_value']} {$ex['unit']}\n";
    }

    echo "\n✨ Migration pricing_config terminée!\n";
    echo "\n💡 Vous pouvez maintenant configurer les prix depuis l'interface admin:\n";
    echo "   Dashboard Admin > Gestion des prix > Configuration détaillée\n";

} catch (PDOException $e) {
    die("❌ Erreur PDO: " . $e->getMessage() . "\n");
}
