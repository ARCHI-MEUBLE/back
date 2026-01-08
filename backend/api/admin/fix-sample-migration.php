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
    // 1. Vérification session Admin
    $session = Session::getInstance();
    if (!$session->has('admin_email')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentification admin requise']);
        exit;
    }

    $db = Database::getInstance();
    $pdo = $db->getPDO();
    $messages = [];

    // Liste des tables et colonnes à ajouter
    $migrations = [
        'sample_types' => ['price_per_m2', 'unit_price'],
        'sample_colors' => ['price_per_m2', 'unit_price']
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
