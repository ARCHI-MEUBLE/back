<?php
/**
 * ArchiMeuble - Script d'initialisation de la base de données
 * Auteur : Collins
 * Date : 2025-10-20
 */

// Chemin vers la base de données
$dbPath = __DIR__ . '/../database.db';

try {
    // Supprimer l'ancienne base si elle existe (pour réinitialisation)
    if (file_exists($dbPath)) {
        unlink($dbPath);
        echo "Ancienne base de données supprimée.\n";
    }

    // Créer la connexion PDO SQLite
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion à la base de données établie.\n";

    // Créer la table templates
    $createTemplatesTable = "
        CREATE TABLE IF NOT EXISTS templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            prompt TEXT NOT NULL,
            base_price REAL NOT NULL,
            image_url TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createTemplatesTable);
    echo "Table 'templates' créée.\n";

    // Créer la table configurations
    $createConfigurationsTable = "
        CREATE TABLE IF NOT EXISTS configurations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_session TEXT NOT NULL,
            prompt TEXT NOT NULL,
            price REAL NOT NULL,
            glb_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createConfigurationsTable);
    echo "Table 'configurations' créée.\n";

    // Insérer les 3 meubles TV
    $insertTemplates = "
        INSERT INTO templates (name, prompt, base_price, image_url) VALUES
        ('Meuble TV Scandinave', 'M1(1700,500,730)EFH3(F,T,F)', 899.00, '/frontend/assets/images/meuble-scandinave.jpg'),
        ('Meuble TV Moderne', 'M1(2000,400,600)EFH2(T,T)', 1099.00, '/frontend/assets/images/meuble-moderne.jpg'),
        ('Meuble TV Compact', 'M1(1200,350,650)EFH4(F,F,T,F)', 699.00, '/frontend/assets/images/meuble-compact.jpg')
    ";
    $pdo->exec($insertTemplates);
    echo "3 meubles TV insérés avec succès.\n";

    // Vérifier les données insérées
    $stmt = $pdo->query("SELECT id, name, base_price FROM templates");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n=== Meubles dans la base de données ===\n";
    foreach ($templates as $template) {
        echo "ID: {$template['id']} | Nom: {$template['name']} | Prix: {$template['base_price']}€\n";
    }

    echo "\n✅ Initialisation de la base de données terminée avec succès!\n";
    echo "Base de données créée à : $dbPath\n";

} catch (PDOException $e) {
    echo "❌ Erreur lors de l'initialisation de la base de données : " . $e->getMessage() . "\n";
    exit(1);
}
