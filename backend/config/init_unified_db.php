<?php
/**
 * Script d'initialisation de la base de données unifiée
 * ArchiMeuble - Backend PHP
 * Date : 2025-10-21
 */

require_once __DIR__ . '/../core/Database.php';

// Lire le script SQL
$sqlScript = file_get_contents(__DIR__ . '/unified_db.sql');

if ($sqlScript === false) {
    die("ERREUR : Impossible de lire le fichier unified_db.sql\n");
}

try {
    // Connexion via la classe Database (PostgreSQL)
    $dbInstance = Database::getInstance();
    $pdo = $dbInstance->getPDO();

    echo "Exécution du script SQL unifié...\n";
    $pdo->exec($sqlScript);
    echo "✓ Script SQL exécuté avec succès\n";

    // Vérifier les tables créées
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTables créées :\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }

    // Créer un utilisateur admin par défaut
    $adminEmail = 'admin@archimeuble.fr';
    $adminPassword = 'admin123';
    $adminPasswordHash = password_hash($adminPassword, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO admins (email, password_hash) VALUES (:email, :password_hash) ON CONFLICT DO NOTHING");
    $stmt->execute([
        'email' => $adminEmail,
        'password_hash' => $adminPasswordHash
    ]);

    echo "\n✓ Administrateur par défaut créé :\n";
    echo "   Email : $adminEmail\n";
    echo "   Mot de passe : $adminPassword\n";

    echo "\n✓ Base de données unifiée initialisée avec succès !\n";

} catch (PDOException $e) {
    echo "✗ ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}
