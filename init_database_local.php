<?php
/**
 * Script temporaire pour initialiser la base de données en local
 */

$dbPath = __DIR__ . '/../database/archimeuble.db';
$sqlPath = __DIR__ . '/../database/init_db.sql';

if (!file_exists($sqlPath)) {
    die("Erreur : Fichier SQL introuvable à : $sqlPath\n");
}

try {
    echo "Création de la base de données...\n";

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents($sqlPath);
    $pdo->exec($sql);

    echo "✓ Base de données créée avec succès !\n";
    echo "✓ Fichier : $dbPath\n";

    // Vérifier
    $count = $pdo->query("SELECT COUNT(*) FROM models")->fetchColumn();
    echo "✓ $count modèles insérés\n";

} catch (PDOException $e) {
    die("✗ Erreur : " . $e->getMessage() . "\n");
}
