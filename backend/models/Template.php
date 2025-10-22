<?php
/**
 * ArchiMeuble - Modèle Template
 * Gère les opérations sur la table templates
 * Auteur : Collins
 * Date : 2025-10-20
 */

require_once __DIR__ . '/../core/Database.php';

class Template {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère tous les templates
     * @return array
     */
    public function getAll() {
        $query = "SELECT * FROM templates ORDER BY created_at DESC";
        return $this->db->query($query);
    }

    /**
     * Récupère un template par son ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $query = "SELECT * FROM templates WHERE id = :id";
        return $this->db->queryOne($query, ['id' => $id]);
    }

    /**
     * Récupère un template par son nom
     * @param string $name
     * @return array|null
     */
    public function getByName($name) {
        $query = "SELECT * FROM templates WHERE name = :name";
        return $this->db->queryOne($query, ['name' => $name]);
    }

    /**
     * Crée un nouveau template
     * @param string $name
     * @param string|null $description
     * @param string $prompt
     * @param float $basePrice
     * @param string $imageUrl
     * @return bool
     */
    public function create($name, $description, $prompt, $basePrice, $imageUrl) {
        $query = "INSERT INTO templates (name, description, prompt, base_price, image_url)
                  VALUES (:name, :description, :prompt, :base_price, :image_url)";

        return $this->db->execute($query, [
            'name' => $name,
            'description' => $description,
            'prompt' => $prompt,
            'base_price' => $basePrice,
            'image_url' => $imageUrl
        ]);
    }

    /**
     * Met à jour un template
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

        $query = "UPDATE templates SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($query, $params);
    }

    /**
     * Supprime un template
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $query = "DELETE FROM templates WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }

    /**
     * Compte le nombre total de templates
     * @return int
     */
    public function count() {
        $query = "SELECT COUNT(*) as total FROM templates";
        $result = $this->db->queryOne($query);
        return $result ? (int)$result['total'] : 0;
    }
}
