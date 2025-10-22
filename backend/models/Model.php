<?php
/**
 * ArchiMeuble - Modèle Model
 * Gère les opérations sur la table models (fusion templates + models)
 * Auteur : Collins
 * Date : 2025-10-21
 */

require_once __DIR__ . '/../core/Database.php';

class Model {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère tous les modèles
     * @return array
     */
    public function getAll() {
        $query = "SELECT * FROM models ORDER BY created_at DESC";
        return $this->db->query($query);
    }

    /**
     * Récupère un modèle par son ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $query = "SELECT * FROM models WHERE id = :id";
        return $this->db->queryOne($query, ['id' => $id]);
    }

    /**
     * Récupère un modèle par son nom
     * @param string $name
     * @return array|null
     */
    public function getByName($name) {
        $query = "SELECT * FROM models WHERE name = :name";
        return $this->db->queryOne($query, ['name' => $name]);
    }

    /**
     * Crée un nouveau modèle
     * @param string $name
     * @param string|null $description
     * @param string $prompt
     * @param float|null $basePrice
     * @param string|null $imagePath
     * @return int|false ID du modèle créé ou false en cas d'erreur
     */
    public function create($name, $description, $prompt, $basePrice = null, $imagePath = null) {
        // Utiliser une requête qui retourne l'ID dans le même appel
        $query = "INSERT INTO models (name, description, prompt, base_price, image_path)
                  VALUES (:name, :description, :prompt, :base_price, :image_path)
                  RETURNING id";

        error_log("SQL Query: " . $query);
        error_log("SQL Params: name=$name, description=$description, prompt=$prompt, base_price=$basePrice, image_path=$imagePath");

        $result = $this->db->queryOne($query, [
            'name' => $name,
            'description' => $description,
            'prompt' => $prompt,
            'base_price' => $basePrice,
            'image_path' => $imagePath
        ]);

        if (!$result) {
            error_log("SQL Error: Failed to insert model");
            return false;
        }

        return (int)$result['id'];
    }

    /**
     * Met à jour un modèle
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = ['name', 'description', 'prompt', 'base_price', 'image_path'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $query = "UPDATE models SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($query, $params);
    }

    /**
     * Supprime un modèle
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $query = "DELETE FROM models WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }

    /**
     * Compte le nombre total de modèles
     * @return int
     */
    public function count() {
        $query = "SELECT COUNT(*) as total FROM models";
        $result = $this->db->queryOne($query);
        return $result ? (int)$result['total'] : 0;
    }
}
