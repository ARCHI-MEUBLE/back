<?php
/**
 * ArchiMeuble - Modèle Configuration
 * Gère les opérations sur la table configurations
 * Auteur : Collins
 * Date : 2025-10-20
 */

require_once __DIR__ . '/../core/Database.php';

class Configuration {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Crée une nouvelle configuration
     * @param string $userSession
     * @param string $prompt
     * @param float $price
     * @param string|null $glbUrl
     * @return int|false ID de la configuration créée ou false en cas d'erreur
     */
    public function create($userSession, $prompt, $price, $glbUrl = null) {
        $query = "INSERT INTO configurations (user_session, prompt, price, glb_url)
                  VALUES (:user_session, :prompt, :price, :glb_url)";

        $success = $this->db->execute($query, [
            'user_session' => $userSession,
            'prompt' => $prompt,
            'price' => $price,
            'glb_url' => $glbUrl
        ]);

        return $success ? $this->db->lastInsertId() : false;
    }

    /**
     * Récupère toutes les configurations
     * @return array
     */
    public function getAll() {
        $query = "SELECT * FROM configurations ORDER BY created_at DESC";
        return $this->db->query($query);
    }

    /**
     * Récupère une configuration par son ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $query = "SELECT * FROM configurations WHERE id = :id";
        return $this->db->queryOne($query, ['id' => $id]);
    }

    /**
     * Récupère toutes les configurations d'une session utilisateur
     * @param string $userSession
     * @return array
     */
    public function getBySession($userSession) {
        $query = "SELECT * FROM configurations WHERE user_session = :user_session ORDER BY created_at DESC";
        return $this->db->query($query, ['user_session' => $userSession]);
    }

    /**
     * Met à jour l'URL du fichier GLB d'une configuration
     * @param int $id
     * @param string $glbUrl
     * @return bool
     */
    public function updateGlbUrl($id, $glbUrl) {
        $query = "UPDATE configurations SET glb_url = :glb_url WHERE id = :id";
        return $this->db->execute($query, [
            'id' => $id,
            'glb_url' => $glbUrl
        ]);
    }

    /**
     * Met à jour une configuration
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $params[$key] = $value;
        }

        $query = "UPDATE configurations SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($query, $params);
    }

    /**
     * Supprime une configuration
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $query = "DELETE FROM configurations WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }

    /**
     * Supprime toutes les configurations d'une session
     * @param string $userSession
     * @return bool
     */
    public function deleteBySession($userSession) {
        $query = "DELETE FROM configurations WHERE user_session = :user_session";
        return $this->db->execute($query, ['user_session' => $userSession]);
    }

    /**
     * Compte le nombre total de configurations
     * @return int
     */
    public function count() {
        $query = "SELECT COUNT(*) as total FROM configurations";
        $result = $this->db->queryOne($query);
        return $result ? (int)$result['total'] : 0;
    }

    /**
     * Compte le nombre de configurations pour une session
     * @param string $userSession
     * @return int
     */
    public function countBySession($userSession) {
        $query = "SELECT COUNT(*) as total FROM configurations WHERE user_session = :user_session";
        $result = $this->db->queryOne($query, ['user_session' => $userSession]);
        return $result ? (int)$result['total'] : 0;
    }
}
