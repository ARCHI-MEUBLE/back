<?php
/**
 * Script pour appliquer la migration des échantillons sur Railway
 * Usage: railway run php backend/database/migrations/apply_to_railway.php
 */

$migrationPath = __DIR__ . '/create_sample_orders.sql';

require_once __DIR__ . '/../../core/Database.php';

echo "🚀 Application de la migration échantillons sur Railway...\n";
echo "📄 Fichier migration: $migrationPath\n\n";

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
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getPDO();

    echo "🔄 Connexion à la base établie\n";

    // Exécuter la migration
    $db->exec($sql);

    echo "✅ Migration appliquée avec succès!\n\n";

    // Vérifier les tables créées
    echo "📊 Vérification des tables créées:\n";
    $stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE '%sample%' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "  ✓ $table\n";
    }

    // Compter les lignes
    echo "\n📈 Statistiques:\n";
    $counts = [
        'sample_types' => $db->query("SELECT COUNT(*) FROM sample_types")->fetchColumn(),
        'sample_colors' => $db->query("SELECT COUNT(*) FROM sample_colors")->fetchColumn(),
        'cart_sample_items' => $db->query("SELECT COUNT(*) FROM cart_sample_items")->fetchColumn(),
        'order_sample_items' => $db->query("SELECT COUNT(*) FROM order_sample_items")->fetchColumn(),
    ];

    foreach ($counts as $table => $count) {
        echo "  - $table: $count lignes\n";
    }

    echo "\n✨ Migration terminée avec succès!\n";

} catch (PDOException $e) {
    die("❌ Erreur PDO: " . $e->getMessage() . "\n");
}
