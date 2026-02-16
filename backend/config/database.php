<?php
/**
 * Configuration de la connexion à la base de données
 * Wrapper pour utiliser la classe Database singleton
 */

require_once __DIR__ . '/../core/Database.php';

/**
 * Récupère une connexion PDO à la base de données
 *
 * @return PDO
 */
function getDbConnection() {
    return Database::getInstance()->getPDO();
}

/**
 * Récupère le DATABASE_URL
 *
 * @return string
 */
function getDatabaseUrl() {
    $url = getenv('DATABASE_URL');
    if (!$url || empty($url)) {
        throw new Exception("DATABASE_URL environment variable is not set");
    }
    return $url;
}
