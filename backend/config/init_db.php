<?php
/**
 * ArchiMeuble - Script d'initialisation de la base de données
 * Auteur : Collins
 * Date : 2025-10-20
 */

require_once __DIR__ . '/../core/Database.php';

try {
    // Connexion via la classe Database (PostgreSQL)
    $dbInstance = Database::getInstance();
    $pdo = $dbInstance->getPDO();
    echo "Connexion à la base de données établie.\n";

    // Créer la table users
    $createUsersTable = "
        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            name TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createUsersTable);
    echo "Table 'users' créée.\n";

    // Créer la table admins
    $createAdminsTable = "
        CREATE TABLE IF NOT EXISTS admins (
            email TEXT PRIMARY KEY,
            password_hash TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createAdminsTable);
    echo "Table 'admins' créée.\n";

    // Créer la table templates
    $createTemplatesTable = "
        CREATE TABLE IF NOT EXISTS templates (
            id SERIAL PRIMARY KEY,
            name TEXT NOT NULL,
            description TEXT,
            prompt TEXT NOT NULL,
            base_price REAL NOT NULL,
            image_url TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createTemplatesTable);
    echo "Table 'templates' créée.\n";

    // Créer la table configurations
    $createConfigurationsTable = "
        CREATE TABLE IF NOT EXISTS configurations (
            id SERIAL PRIMARY KEY,
            user_id TEXT,
            template_id INTEGER,
            config_string TEXT NOT NULL,
            price REAL NOT NULL,
            glb_url TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (template_id) REFERENCES templates(id)
        )
    ";
    $pdo->exec($createConfigurationsTable);
    echo "Table 'configurations' créée.\n";

    // Insérer un admin par défaut (email: admin@archimeuble.fr, password: admin123)
    $adminPasswordHash = password_hash('admin123', PASSWORD_BCRYPT);
    $insertAdmin = "
        INSERT INTO admins (email, password_hash) VALUES
        ('admin@archimeuble.fr', '$adminPasswordHash')
    ";
    $pdo->exec($insertAdmin);
    echo "Admin par défaut créé (email: admin@archimeuble.fr, password: admin123).\n";

    // Insérer les 3 meubles TV
    $insertTemplates = "
        INSERT INTO templates (name, description, prompt, base_price, image_url) VALUES
        ('Meuble TV Scandinave', 'Design épuré et fonctionnel inspiré du style scandinave', 'M1(1700,500,730)EFH3(F,T,F)', 899.00, 'meuble1.png'),
        ('Meuble TV Moderne', 'Lignes contemporaines avec finitions élégantes', 'M1(2000,400,600)EFH2(T,T)', 1099.00, 'meuble2.png'),
        ('Meuble TV Compact', 'Solution compacte et pratique pour petits espaces', 'M1(1200,350,650)EFH4(F,F,T,F)', 699.00, 'meuble3.png')
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

} catch (PDOException $e) {
    echo "❌ Erreur lors de l'initialisation de la base de données : " . $e->getMessage() . "\n";
    exit(1);
}
