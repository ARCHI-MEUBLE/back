<?php
/**
 * ArchiMeuble - Fix Migration Échantillons
 * Ajoute les colonnes manquantes sans passer par les modèles
 */

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Vérification session Admin OU Token de secours
    $session = Session::getInstance();
    $token = $_GET['token'] ?? '';
    $isValidToken = ($token === 'archimeuble_fix_2025');

    if (!$session->has('admin_email') && !$isValidToken) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Authentification admin requise',
            'tip' => 'Utilisez le lien avec le token de secours si vous êtes bloqué.'
        ]);
        exit;
    }

    $db = Database::getInstance();
    $pdo = $db->getPDO();
    $messages = [];

    // --- SECTION CRITIQUE : CREATION DES TABLES ---
    
    // 1. Table calendly_appointments (avec structure robuste)
    $pdo->exec("CREATE TABLE IF NOT EXISTS calendly_appointments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_uri TEXT UNIQUE,
        invitee_uri TEXT,
        event_type_uri TEXT,
        start_time DATETIME,
        end_time DATETIME,
        invitee_name TEXT,
        invitee_email TEXT,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $messages[] = "✅ Table 'calendly_appointments' créée ou déjà présente";

    // 2. Table categories (nécessaire pour le configurateur)
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT UNIQUE,
        description TEXT,
        image_url TEXT,
        is_active INTEGER DEFAULT 1,
        display_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $messages[] = "✅ Table 'categories' créée ou déjà présente";

    // 3. Table pricing (pour les prix dynamiques)
    $pdo->exec("CREATE TABLE IF NOT EXISTS pricing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE,
        value REAL,
        description TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $messages[] = "✅ Table 'pricing' créée ou déjà présente";

    // 4. Table payment_links (pour les liens de paiement Stripe)
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        token TEXT UNIQUE,
        stripe_link_id TEXT,
        url TEXT,
        amount REAL,
        status TEXT DEFAULT 'active',
        payment_type TEXT DEFAULT 'full',
        created_by_admin TEXT,
        accessed_at DATETIME,
        paid_at DATETIME,
        expires_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id)
    )");
    $messages[] = "✅ Table 'payment_links' synchronisée avec le modèle PHP";

    // --- SECTION MIGRATIONS COLONNES ---
    $migrations = [
        'sample_types' => ['price_per_m2', 'unit_price'],
        'sample_colors' => ['price_per_m2', 'unit_price'],
        'orders' => ['payment_strategy', 'deposit_percentage', 'deposit_amount', 'remaining_amount', 'deposit_payment_status']
    ];

    foreach ($migrations as $table => $columns) {
        // Récupérer les colonnes existantes
        $stmt = $pdo->query("PRAGMA table_info($table)");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

        foreach ($columns as $column) {
            if (!in_array($column, $existingColumns)) {
                try {
                    $pdo->exec("ALTER TABLE $table ADD COLUMN $column REAL DEFAULT 0.0");
                    $messages[] = "✅ Colonne '$column' ajoutée à '$table'";
                } catch (Exception $e) {
                    $messages[] = "❌ Erreur sur '$table.$column': " . $e->getMessage();
                }
            } else {
                $messages[] = "ℹ️ '$table.$column' existe déjà";
            }
        }
    }

    echo json_encode([
        'success' => true,
        'results' => $messages,
        'info' => 'La structure de la base de données est maintenant à jour.'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
