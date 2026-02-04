<?php
/**
 * Migration: Ajout du système de vérification email
 * Exécuter avec: php run_email_verification.php
 */

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();

    // Vérifier si la colonne existe déjà
    $pdo = $db->getPDO();
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = :table AND table_schema = 'public'");
    $stmt->execute(['table' => 'customers']);
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasVerifiedColumn = in_array('email_verified', $columns);

    if (!$hasVerifiedColumn) {
        $db->execute("ALTER TABLE customers ADD COLUMN email_verified BOOLEAN DEFAULT FALSE");
        echo "✓ Colonne 'email_verified' ajoutée à la table customers\n";

        // Marquer tous les utilisateurs existants comme vérifiés
        $db->execute("UPDATE customers SET email_verified = TRUE");
        echo "✓ Tous les clients existants marqués comme vérifiés\n";
    } else {
        echo "- Colonne 'email_verified' existe déjà\n";
    }

    // Créer la table email_verifications
    $db->execute("
        CREATE TABLE IF NOT EXISTS email_verifications (
            id SERIAL PRIMARY KEY,
            email TEXT NOT NULL,
            code TEXT NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            used INTEGER DEFAULT 0
        )
    ");
    echo "✓ Table 'email_verifications' créée\n";

    // Créer les index
    $db->execute("CREATE INDEX IF NOT EXISTS idx_email_verifications_email ON email_verifications(email)");
    $db->execute("CREATE INDEX IF NOT EXISTS idx_email_verifications_expires ON email_verifications(expires_at)");
    echo "✓ Index créés\n";

    echo "\n✅ Migration terminée avec succès!\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
