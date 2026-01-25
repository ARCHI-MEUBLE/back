<?php
/**
 * Configuration de la connexion à la base de données
 * Wrapper pour utiliser la classe Database singleton
 */

require_once __DIR__ . '/../core/Database.php';

/**
 * Récupère une connexion à la base de données
 * Retourne un objet SQLite3 pour compatibilité avec l'ancien code
 * 
 * @return SQLite3
 */
function getDbConnection() {
    // Utiliser la classe Database existante
    $db = Database::getInstance();
    
    // Récupérer le chemin de la base de données
    $dbPath = getenv('DB_PATH');
    
    if (!$dbPath || empty($dbPath)) {
        // Priorités comme dans Database.php
        if (file_exists('/data/archimeuble.db')) {
            $dbPath = '/data/archimeuble.db';
        } elseif (file_exists('/app/database/archimeuble.db')) {
            $dbPath = '/app/database/archimeuble.db';
        } else {
            $dbPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'archimeuble.db';
        }
    }
    
    // Créer une nouvelle connexion SQLite3
    try {
        $sqlite = new SQLite3($dbPath);
        $sqlite->busyTimeout(5000); // Timeout de 5 secondes
        return $sqlite;
    } catch (Exception $e) {
        error_log("Erreur de connexion SQLite3 : " . $e->getMessage());
        throw new Exception("Impossible de se connecter à la base de données");
    }
}

/**
 * Récupère le chemin de la base de données
 * 
 * @return string
 */
function getDbPath() {
    $dbPath = getenv('DB_PATH');
    
    if (!$dbPath || empty($dbPath)) {
        if (file_exists('/data/archimeuble.db')) {
            return '/data/archimeuble.db';
        } elseif (file_exists('/app/database/archimeuble.db')) {
            return '/app/database/archimeuble.db';
        } else {
            return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'archimeuble.db';
        }
    }
    
    return $dbPath;
}
