<?php
/**
 * Script temporaire pour supprimer les modèles par défaut
 * À exécuter une seule fois
 */

$dbPath = getenv('DB_PATH') ?: '/app/database/archimeuble.db';

if (!file_exists($dbPath)) {
    die("Base de données introuvable: $dbPath\n");
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Supprimer les modèles par défaut (IDs 1, 2, 3)
    $stmt = $pdo->prepare("DELETE FROM models WHERE id IN (1, 2, 3)");
    $stmt->execute();

    echo "✅ Modèles par défaut supprimés avec succès!\n";
    echo "Nombre de modèles supprimés: " . $stmt->rowCount() . "\n";

    // Afficher les modèles restants
    $stmt = $pdo->query("SELECT id, name FROM models");
    $remaining = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nModèles restants:\n";
    if (count($remaining) > 0) {
        foreach ($remaining as $model) {
            echo "  - [{$model['id']}] {$model['name']}\n";
        }
    } else {
        echo "  (aucun)\n";
    }

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
