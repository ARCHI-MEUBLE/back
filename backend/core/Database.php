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
        // Dans Docker: /app/database/archimeuble.db
        // En local: ../database/archimeuble.db (depuis back/)
        $dbPath = getenv('DB_PATH');

        if (!$dbPath) {
            // Chemin local relatif
            $dbPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'archimeuble.db';
        }

        $this->dbPath = $dbPath;

        if (!file_exists($this->dbPath)) {
            error_log("Erreur : Base de données introuvable à : " . $this->dbPath);
            throw new Exception("Base de données introuvable à : " . $this->dbPath);
        }

        // Créer la connexion PDO
        try {
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
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
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur de requête : " . $e->getMessage());
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
