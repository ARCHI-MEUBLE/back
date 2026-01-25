<?php
/**
 * Script de migration pour créer la table calendly_appointments
 * À exécuter une seule fois pour créer la table sur Railway
 */

// Afficher les erreurs pour debug
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // Connexion directe à SQLite sans passer par la classe Database
    $dbPath = getenv('DB_PATH') ?: '/app/database/archimeuble.db';

    if (!file_exists($dbPath)) {
        throw new Exception("Base de données introuvable: $dbPath");
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Créer la table calendly_appointments
    $sql = "CREATE TABLE IF NOT EXISTS calendly_appointments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        calendly_event_id TEXT UNIQUE NOT NULL,
        client_name TEXT NOT NULL,
        client_email TEXT NOT NULL,
        event_type TEXT,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        timezone TEXT DEFAULT 'Europe/Paris',
        config_url TEXT,
        additional_notes TEXT,
        meeting_url TEXT,
        phone_number TEXT,
        status TEXT DEFAULT 'scheduled',
        confirmation_sent INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    $db->exec($sql);

    // Vérifier que la table a été créée
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='calendly_appointments'");
    $table = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($table) {
        echo json_encode([
            'success' => true,
            'message' => 'Table calendly_appointments créée avec succès',
            'table' => $table
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'La table n\'a pas été créée'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
