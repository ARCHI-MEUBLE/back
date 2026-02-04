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
    $pdo = $db->getPDO(); // Utiliser PDO directement pour ALTER TABLE
    $results = [];

    // Helper pour vérifier si une colonne existe
    function columnExists($pdo, $table, $column) {
        try {
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = :table AND table_schema = 'public'");
            $stmt->execute(['table' => $table]);
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            return in_array($column, $columns);
        } catch (Exception $e) {
            return false;
        }
    }

    // 1. Vérifier et ajouter price_per_m2 à sample_types
    if (!columnExists($pdo, 'sample_types', 'price_per_m2')) {
        try {
            $pdo->exec("ALTER TABLE sample_types ADD COLUMN price_per_m2 REAL DEFAULT 0.0");
            $results[] = "✅ Colonne price_per_m2 ajoutée à sample_types";
        } catch (Exception $e) {
            $results[] = "❌ Erreur price_per_m2 sur sample_types: " . $e->getMessage();
        }
    } else {
        $results[] = "ℹ️ Colonne price_per_m2 existe déjà dans sample_types";
    }

    // 2. Vérifier et ajouter unit_price à sample_types
    if (!columnExists($pdo, 'sample_types', 'unit_price')) {
        try {
            $pdo->exec("ALTER TABLE sample_types ADD COLUMN unit_price REAL DEFAULT 0.0");
            $results[] = "✅ Colonne unit_price ajoutée à sample_types";
        } catch (Exception $e) {
            $results[] = "❌ Erreur unit_price sur sample_types: " . $e->getMessage();
        }
    } else {
        $results[] = "ℹ️ Colonne unit_price existe déjà dans sample_types";
    }

    // 3. Vérifier et ajouter price_per_m2 à sample_colors
    if (!columnExists($pdo, 'sample_colors', 'price_per_m2')) {
        try {
            $pdo->exec("ALTER TABLE sample_colors ADD COLUMN price_per_m2 REAL DEFAULT 0.0");
            $results[] = "✅ Colonne price_per_m2 ajoutée à sample_colors";
        } catch (Exception $e) {
            $results[] = "❌ Erreur price_per_m2 sur sample_colors: " . $e->getMessage();
        }
    } else {
        $results[] = "ℹ️ Colonne price_per_m2 existe déjà dans sample_colors";
    }

    // 4. Vérifier et ajouter unit_price à sample_colors
    if (!columnExists($pdo, 'sample_colors', 'unit_price')) {
        try {
            $pdo->exec("ALTER TABLE sample_colors ADD COLUMN unit_price REAL DEFAULT 0.0");
            $results[] = "✅ Colonne unit_price ajoutée à sample_colors";
        } catch (Exception $e) {
            $results[] = "❌ Erreur unit_price sur sample_colors: " . $e->getMessage();
        }
    } else {
        $results[] = "ℹ️ Colonne unit_price existe déjà dans sample_colors";
    }

    // 5. Vérification finale
    $finalCheck = [
        'sample_types_price_per_m2' => columnExists($pdo, 'sample_types', 'price_per_m2'),
        'sample_types_unit_price' => columnExists($pdo, 'sample_types', 'unit_price'),
        'sample_colors_price_per_m2' => columnExists($pdo, 'sample_colors', 'price_per_m2'),
        'sample_colors_unit_price' => columnExists($pdo, 'sample_colors', 'unit_price')
    ];

    $allColumnsExist = !in_array(false, $finalCheck, true);

    if ($allColumnsExist) {
        $results[] = "✅ Vérification finale réussie : toutes les colonnes sont présentes";
    } else {
        $results[] = "❌ Vérification finale : certaines colonnes manquent encore";
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
