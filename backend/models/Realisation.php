oui<?php
/**
 * ArchiMeuble - Modèle Realisation
 * Gère les opérations sur la table realisations
 */

require_once __DIR__ . '/../core/Database.php';

class Realisation {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère toutes les réalisations
     * @return array
     */
    public function getAll() {
        $query = "SELECT * FROM realisations ORDER BY created_at DESC";
        return $this->db->query($query);
    }

    /**
     * Récupère une réalisation par son ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $query = "SELECT * FROM realisations WHERE id = :id";
        return $this->db->queryOne($query, ['id' => $id]);
    }

    /**
     * Crée une nouvelle réalisation
     * @param array $data
     * @return int|false ID de la réalisation créée ou false en cas d'erreur
     */
    public function create($data) {
        $query = "INSERT INTO realisations (titre, description, image_url, date_projet, categorie, lieu, dimensions)
                  VALUES (:titre, :description, :image_url, :date_projet, :categorie, :lieu, :dimensions)";

        $success = $this->db->execute($query, [
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'date_projet' => $data['date_projet'] ?? null,
            'categorie' => $data['categorie'] ?? null,
            'lieu' => $data['lieu'] ?? null,
            'dimensions' => $data['dimensions'] ?? null
        ]);

        if (!$success) {
            return false;
        }

        return $this->db->lastInsertId();
    }

    /**
     * Met à jour une réalisation
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = ['titre', 'description', 'image_url', 'date_projet', 'categorie', 'lieu', 'dimensions'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $query = "UPDATE realisations SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($query, $params);
    }

    /**
     * Supprime une réalisation
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $query = "DELETE FROM realisations WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }
}
?>
