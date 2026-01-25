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
     * @param string|null $userId
     * @param int|null $templateId
     * @param string $configString
     * @param float $price
     * @param string|null $glbUrl
     * @param string|null $prompt
     * @param string|null $userSession
     * @return int|false ID de la configuration créée ou false en cas d'erreur
     */
    public function create($userId, $templateId, $configString, $price, $glbUrl = null, $prompt = null, $userSession = null, $status = 'en_attente_validation') {
        $query = "INSERT INTO configurations (user_id, user_session, template_id, config_string, prompt, price, glb_url, status)
                  VALUES (:user_id, :user_session, :template_id, :config_string, :prompt, :price, :glb_url, :status)";

        $success = $this->db->execute($query, [
            'user_id' => $userId,
            'user_session' => $userSession,
            'template_id' => $templateId,
            'config_string' => $configString,
            'prompt' => $prompt,
            'price' => $price,
            'glb_url' => $glbUrl,
            'status' => $status
        ]);

        return $success ? $this->db->lastInsertId() : false;
    }

    /**
     * Récupère toutes les configurations
     * @return array
     */
    public function getAll() {
        $query = "SELECT c.*
                  FROM configurations c
                  ORDER BY c.created_at DESC";
        return $this->db->query($query);
    }

    /**
     * Récupère une configuration par son ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $query = "SELECT c.*, oi.order_id
                  FROM configurations c
                  LEFT JOIN order_items oi ON c.id = oi.configuration_id
                  WHERE c.id = :id
                  ORDER BY oi.id DESC
                  LIMIT 1";
        return $this->db->queryOne($query, ['id' => $id]);
    }

    /**
     * Récupère toutes les configurations d'un utilisateur
     * @param string $userId
     * @return array
     */
    public function getByUserId($userId) {
        // Ne pas retourner les configurations qui font partie d'une commande payée
        $query = "SELECT c.*,
                         (SELECT MAX(order_id) FROM order_items WHERE configuration_id = c.id) as order_id,
                         (SELECT o.payment_status FROM orders o
                          JOIN order_items oi ON o.id = oi.order_id
                          WHERE oi.configuration_id = c.id
                          LIMIT 1) as order_payment_status
                  FROM configurations c
                  WHERE c.user_id = :user_id
                  -- Exclure les configurations déjà payées
                  AND NOT EXISTS (
                      SELECT 1 FROM order_items oi
                      JOIN orders o ON oi.order_id = o.id
                      WHERE oi.configuration_id = c.id
                      AND o.payment_status = 'paid'
                  )
                  ORDER BY c.created_at DESC";
        return $this->db->query($query, ['user_id' => $userId]);
    }

    /**
     * Récupère toutes les configurations par session
     * @param string $userSession
     * @return array
     */
    public function getBySession($userSession) {
        $query = "SELECT c.*
                  FROM configurations c
                  WHERE c.user_session = :user_session
                  ORDER BY c.created_at DESC";
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
     * Supprime toutes les configurations d'un utilisateur
     * @param string $userId
     * @return bool
     */
    public function deleteByUserId($userId) {
        $query = "DELETE FROM configurations WHERE user_id = :user_id";
        return $this->db->execute($query, ['user_id' => $userId]);
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
     * Compte le nombre de configurations pour un utilisateur
     * @param string $userId
     * @return int
     */
    public function countByUserId($userId) {
        $query = "SELECT COUNT(*) as total FROM configurations WHERE user_id = :user_id";
        $result = $this->db->queryOne($query, ['user_id' => $userId]);
        return $result ? (int)$result['total'] : 0;
    }
}
