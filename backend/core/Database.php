<?php
/**
 * ArchiMeuble - Classe Database (Singleton)
 * Utilise PDO SQLite (compatible Docker)
 * Auteur : Collins
 * Date : 2025-10-20
 */

class Database {
    private static $instance = null;
    private $dbPath;
    private $pdo;

    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private function __construct() {
        // Vérifier si on est dans Docker ou en local
        // Production Railway: /data/archimeuble.db (volume persistant recommandé)
        // Local: /app/database/archimeuble.db
        $dbPath = getenv('DB_PATH');

        if (!$dbPath || empty($dbPath)) {
            // Priorité 1: Chemin standard Railway Volume
            if (file_exists('/data/archimeuble.db')) {
                $dbPath = '/data/archimeuble.db';
            } 
            // Priorité 2: Dossier database de l'application
            elseif (file_exists('/app/database/archimeuble.db')) {
                $dbPath = '/app/database/archimeuble.db';
            }
            // Priorité 3: Chemin relatif au projet
            else {
                $dbPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'archimeuble.db';
            }
        }

        $this->dbPath = $dbPath;
        error_log("Database: Loading SQLite DB from: " . $this->dbPath);

        if (!file_exists($this->dbPath)) {
            error_log("CRITICAL: Database file not found at " . $this->dbPath);
            // Tentative de création si le dossier existe
            $dir = dirname($this->dbPath);
            if (is_writable($dir)) {
                error_log("Database: Folder is writable, file will be created on first connection.");
            } else {
                throw new Exception("Base de données introuvable et dossier non scriptable : " . $this->dbPath);
            }
        }

        // Créer la connexion PDO
        try {
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Forcer l'encodage UTF-8 pour SQLite
            $this->pdo->exec("PRAGMA encoding = 'UTF-8'");
        } catch (PDOException $e) {
            error_log("Erreur de connexion PDO : " . $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données");
        }
    }

    /**
     * Empêche le clonage de l'instance
     */
    private function __clone() {}

    /**
     * Empêche la désérialisation de l'instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Retourne l'instance unique de la classe
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne l'instance PDO
     * @return PDO
     */
    public function getPDO() {
        return $this->pdo;
    }

    /**
     * Exécute une requête SELECT et retourne tous les résultats
     * @param string $query
     * @param array $params
     * @return array
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            error_log("✅ Query réussie, " . count($results) . " lignes retournées");
            return $results;
        } catch (PDOException $e) {
            error_log("❌❌❌ ERREUR DE REQUÊTE SQL ❌❌❌");
            error_log("Message: " . $e->getMessage());
            error_log("Query: " . $query);
            error_log("Params: " . print_r($params, true));
            return [];
        }
    }

    /**
     * Exécute une requête SELECT et retourne un seul résultat
     * @param string $query
     * @param array $params
     * @return array|null
     */
    public function queryOne($query, $params = []) {
        $results = $this->query($query, $params);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Exécute une requête INSERT, UPDATE ou DELETE
     * @param string $query
     * @param array $params
     * @return bool
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erreur d'exécution : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retourne l'ID du dernier enregistrement inséré
     * @return int
     */
    public function lastInsertId() {
        return (int)$this->pdo->lastInsertId();
    }
}
//