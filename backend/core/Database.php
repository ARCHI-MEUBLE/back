<?php
/**
 * ArchiMeuble - Classe Database (Singleton)
 * Utilise sqlite3.exe directement via shell_exec
 * Auteur : Collins
 * Date : 2025-10-20
 */

class Database {
    private static $instance = null;
    private $dbPath;
    private $sqlite3Command = 'F:\\ANACONDA\\Library\\bin\\sqlite3.exe';

    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private function __construct() {
        // Utiliser la base de données unifiée partagée avec le frontend
        $this->dbPath = __DIR__ . '/../../database/archimeuble.db';

        if (!file_exists($this->dbPath)) {
            die("Erreur : Base de données introuvable à : " . $this->dbPath);
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
     * Échappe une valeur pour SQLite
     * @param mixed $value
     * @return string
     */
    private function escape($value) {
        if ($value === null) {
            return 'NULL';
        }
        // Remplacer les apostrophes simples par deux apostrophes
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Remplace les paramètres nommés par leurs valeurs
     * @param string $query
     * @param array $params
     * @return string
     */
    private function bindParams($query, $params) {
        foreach ($params as $key => $value) {
            $placeholder = ':' . $key;
            $escapedValue = $this->escape($value);
            $query = str_replace($placeholder, $escapedValue, $query);
        }
        return $query;
    }

    /**
     * Exécute une requête SQL via sqlite3.exe
     * @param string $query
     * @return string
     */
    private function executeSQL($query) {
        // Supprimer les retours à la ligne et espaces multiples
        $query = preg_replace('/\s+/', ' ', trim($query));

        // Échapper les guillemets pour le shell
        $query = str_replace('"', '""', $query);

        // Construire la commande
        $command = sprintf(
            '"%s" "%s" ".mode json" ".once stdout" "%s"',
            $this->sqlite3Command,
            $this->dbPath,
            $query
        );

        // Exécuter la commande
        $output = shell_exec($command . ' 2>&1');

        // Ne pas convertir null en chaîne vide pour pouvoir distinguer succès/échec
        return $output;
    }

    /**
     * Exécute une requête SELECT et retourne tous les résultats
     * @param string $query
     * @param array $params
     * @return array
     */
    public function query($query, $params = []) {
        try {
            $query = $this->bindParams($query, $params);
            $output = $this->executeSQL($query);

            // Parser le JSON retourné
            $result = json_decode($output, true);

            return is_array($result) ? $result : [];
        } catch (Exception $e) {
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
            $query = $this->bindParams($query, $params);
            error_log("Final SQL Query: " . $query);
            $output = $this->executeSQL($query);
            error_log("SQL Output: " . ($output ?? 'NULL'));

            // Si pas d'erreur, c'est OK
            // Pour INSERT/UPDATE/DELETE, SQLite ne retourne rien (null ou vide) si succès
            // Seulement en cas d'erreur il y aura "Error:" dans la sortie
            $hasError = $output !== null && $output !== '' && str_contains($output, 'Error:');
            error_log("Has error: " . ($hasError ? 'yes' : 'no'));
            return !$hasError;
        } catch (Exception $e) {
            error_log("Erreur d'exécution : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retourne l'ID du dernier enregistrement inséré
     * @return int
     */
    public function lastInsertId() {
        $result = $this->queryOne("SELECT last_insert_rowid() as id");
        return $result ? (int)$result['id'] : 0;
    }
}
