<?php
/**
 * Script temporaire pour créer un nouvel administrateur
 * À exécuter une seule fois sur Railway
 */

// Charger la base de données
$dbPath = getenv('DB_PATH') ?: '/app/database/archimeuble.db';

if (!file_exists($dbPath)) {
    die("Base de données introuvable: $dbPath\n");
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Identifiants du nouvel admin
$username = 'archimeuble';
$email = 'contact@archimeuble.com';
$password = 'Archimeuble2025!';

// Hasher le mot de passe
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

try {
    // Insérer le nouvel admin
    $stmt = $pdo->prepare("INSERT INTO admins (username, password, email) VALUES (?, ?, ?)");
    $stmt->execute([$username, $passwordHash, $email]);

    echo "✅ Nouvel administrateur créé avec succès!\n";
    echo "Username: $username\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
