<?php
/**
 * Script pour réinitialiser le mot de passe admin
 * Usage: php reset-admin-password.php
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Admin.php';

$email = 'b@gmail.com';
$newPassword = '12345678';

// Hash le mot de passe
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

echo "=== Réinitialisation du mot de passe admin ===\n";
echo "Email: $email\n";
echo "Nouveau mot de passe: $newPassword\n";
echo "Hash: $passwordHash\n\n";

$admin = new Admin();

// Vérifier si l'admin existe
$existingAdmin = $admin->getByEmail($email);

if (!$existingAdmin) {
    echo "❌ Admin '$email' n'existe pas.\n";
    echo "Création du compte admin...\n";

    $success = $admin->create($email, $passwordHash, 'b');

    if ($success) {
        echo "✅ Admin créé avec succès !\n";
    } else {
        echo "❌ Erreur lors de la création de l'admin.\n";
        exit(1);
    }
} else {
    echo "✓ Admin trouvé (ID: {$existingAdmin['id']})\n";
    echo "Mise à jour du mot de passe...\n";

    $success = $admin->updatePassword($email, $passwordHash);

    if ($success) {
        echo "✅ Mot de passe mis à jour avec succès !\n";
    } else {
        echo "❌ Erreur lors de la mise à jour du mot de passe.\n";
        exit(1);
    }
}

echo "\n=== Vérification ===\n";
$verifiedAdmin = $admin->verifyCredentials($email, $newPassword);

if ($verifiedAdmin) {
    echo "✅ Connexion testée avec succès !\n";
    echo "Vous pouvez maintenant vous connecter avec:\n";
    echo "  Email: $email\n";
    echo "  Mot de passe: $newPassword\n";
} else {
    echo "❌ Échec de la vérification des identifiants.\n";
    exit(1);
}

echo "\n✅ Terminé !\n";
