<?php
/**
 * Migration : Ajouter les colonnes de tarification aux échantillons
 * GET /api/admin/migrate-sample-pricing
 *
 * Script à usage unique pour ajouter price_per_m2 et unit_price
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Database.php';

try {
    // Vérifier l'authentification admin
    $session = Session::getInstance();
    if (!$session->has('admin_email') || $session->get('is_admin') !== true) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Non authentifié'
        ]);
        exit;
    }

    $db = Database::getInstance();
    $results = [];

    // 1. Vérifier si les colonnes existent déjà dans sample_types
    try {
        $testQuery = "SELECT price_per_m2, unit_price FROM sample_types LIMIT 1";
        $db->query($testQuery, []);
        $results[] = "✅ Les colonnes existent déjà dans sample_types";
        $sampleTypesNeedMigration = false;
    } catch (Exception $e) {
        $results[] = "ℹ️ Les colonnes n'existent pas encore dans sample_types";
        $sampleTypesNeedMigration = true;
    }

    // 2. Vérifier si les colonnes existent déjà dans sample_colors
    try {
        $testQuery = "SELECT price_per_m2, unit_price FROM sample_colors LIMIT 1";
        $db->query($testQuery, []);
        $results[] = "✅ Les colonnes existent déjà dans sample_colors";
        $sampleColorsNeedMigration = false;
    } catch (Exception $e) {
        $results[] = "ℹ️ Les colonnes n'existent pas encore dans sample_colors";
        $sampleColorsNeedMigration = true;
    }

    // 3. Ajouter les colonnes à sample_types si nécessaire
    if ($sampleTypesNeedMigration) {
        try {
            $db->execute("ALTER TABLE sample_types ADD COLUMN price_per_m2 REAL DEFAULT 0.0", []);
            $results[] = "✅ Colonne price_per_m2 ajoutée à sample_types";
        } catch (Exception $e) {
            $results[] = "⚠️ Erreur price_per_m2 sur sample_types: " . $e->getMessage();
        }

        try {
            $db->execute("ALTER TABLE sample_types ADD COLUMN unit_price REAL DEFAULT 0.0", []);
            $results[] = "✅ Colonne unit_price ajoutée à sample_types";
        } catch (Exception $e) {
            $results[] = "⚠️ Erreur unit_price sur sample_types: " . $e->getMessage();
        }
    }

    // 4. Ajouter les colonnes à sample_colors si nécessaire
    if ($sampleColorsNeedMigration) {
        try {
            $db->execute("ALTER TABLE sample_colors ADD COLUMN price_per_m2 REAL DEFAULT 0.0", []);
            $results[] = "✅ Colonne price_per_m2 ajoutée à sample_colors";
        } catch (Exception $e) {
            $results[] = "⚠️ Erreur price_per_m2 sur sample_colors: " . $e->getMessage();
        }

        try {
            $db->execute("ALTER TABLE sample_colors ADD COLUMN unit_price REAL DEFAULT 0.0", []);
            $results[] = "✅ Colonne unit_price ajoutée à sample_colors";
        } catch (Exception $e) {
            $results[] = "⚠️ Erreur unit_price sur sample_colors: " . $e->getMessage();
        }
    }

    // 5. Vérification finale
    try {
        $testTypes = $db->query("SELECT id, name, price_per_m2, unit_price FROM sample_types LIMIT 1", []);
        $testColors = $db->query("SELECT id, name, price_per_m2, unit_price FROM sample_colors LIMIT 1", []);
        $results[] = "✅ Vérification finale réussie : les colonnes sont accessibles";
    } catch (Exception $e) {
        $results[] = "❌ Vérification finale échouée : " . $e->getMessage();
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Migration terminée',
        'results' => $results,
        'migrations_needed' => [
            'sample_types' => $sampleTypesNeedMigration,
            'sample_colors' => $sampleColorsNeedMigration
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
