<?php
// Script pour mettre à jour le mot de passe admin

require_once __DIR__ . '/backend/core/Database.php';

$db = Database::getInstance();

// Générer le hash bcrypt
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Hash généré: $hash\n";

// Mettre à jour le mot de passe
$query = "UPDATE admins SET password = :password WHERE username = 'admin'";
$result = $db->execute($query, ['password' => $hash]);

if ($result) {
    echo "✓ Mot de passe mis à jour avec succès!\n";

    // Vérifier
    $query = "SELECT username, email FROM admins WHERE username = 'admin'";
    $admin = $db->queryOne($query);

    if ($admin) {
        echo "Admin trouvé: {$admin['username']} ({$admin['email']})\n";
    }
} else {
    echo "✗ Erreur lors de la mise à jour\n";
}
?>
