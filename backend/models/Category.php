<?php
/**
 * ArchiMeuble - Modèle Category
 * Gère les opérations sur la table categories
 * Auteur : Collins
 * Date : 2026-01-08
 */

require_once __DIR__ . '/../core/Database.php';

class Category {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère toutes les catégories actives
     * @param bool $onlyActive Si true, ne récupère que les catégories actives
     * @return array
     */
    public function getAll($onlyActive = false) {
        $query = "SELECT * FROM categories";
        if ($onlyActive) {
            $query .= " WHERE is_active = 1";
        }
        $query .= " ORDER BY display_order ASC, name ASC";
        return $this->db->query($query);
    }

    /**
     * Récupère une catégorie par son ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $query = "SELECT * FROM categories WHERE id = :id";
        return $this->db->queryOne($query, ['id' => $id]);
    }

    /**
     * Récupère une catégorie par son slug
     * @param string $slug
     * @return array|null
     */
    public function getBySlug($slug) {
        $query = "SELECT * FROM categories WHERE slug = :slug";
        return $this->db->queryOne($query, ['slug' => $slug]);
    }

    /**
     * Crée une nouvelle catégorie
     * @param string $name
     * @param string $slug
     * @param string|null $description
     * @param string|null $imageUrl
     * @param int $displayOrder
     * @param bool $isActive
     * @return int|false ID de la catégorie créée ou false en cas d'erreur
     */
    public function create($name, $slug, $description = null, $imageUrl = null, $displayOrder = 0, $isActive = true) {
        // Vérifier si le slug existe déjà
        $existing = $this->getBySlug($slug);
        if ($existing) {
            error_log("Category with slug '$slug' already exists");
            return false;
        }

        $query = "INSERT INTO categories (name, slug, description, image_url, display_order, is_active)
                  VALUES (:name, :slug, :description, :image_url, :display_order, :is_active)";

        $success = $this->db->execute($query, [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'image_url' => $imageUrl,
            'display_order' => $displayOrder,
            'is_active' => $isActive ? 1 : 0
        ]);

        if (!$success) {
            error_log("Failed to insert category");
            return false;
        }

        return $this->db->lastInsertId();
    }

    /**
     * Met à jour une catégorie
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = ['name', 'slug', 'description', 'image_url', 'display_order', 'is_active'];

        // Vérifier si le slug est modifié et s'il existe déjà
        if (isset($data['slug'])) {
            $existing = $this->getBySlug($data['slug']);
            if ($existing && $existing['id'] != $id) {
                error_log("Category with slug '{$data['slug']}' already exists");
                return false;
            }
        }

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        // Ajouter updated_at
        $fields[] = "updated_at = CURRENT_TIMESTAMP";

        $query = "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($query, $params);
    }

    /**
     * Supprime une catégorie
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        // Vérifier si des modèles utilisent cette catégorie
        $checkQuery = "SELECT COUNT(*) as count FROM models WHERE category = (SELECT slug FROM categories WHERE id = :id)";
        $result = $this->db->queryOne($checkQuery, ['id' => $id]);

        if ($result && $result['count'] > 0) {
            error_log("Cannot delete category with ID $id: it is used by {$result['count']} models");
            return false;
        }

        $query = "DELETE FROM categories WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }

    /**
     * Compte le nombre total de catégories
     * @param bool $onlyActive
     * @return int
     */
    public function count($onlyActive = false) {
        $query = "SELECT COUNT(*) as total FROM categories";
        if ($onlyActive) {
            $query .= " WHERE is_active = 1";
        }
        $result = $this->db->queryOne($query);
        return $result ? (int)$result['total'] : 0;
    }

    /**
     * Réorganise l'ordre d'affichage des catégories
     * @param array $categoryIds Tableau des IDs dans le nouvel ordre
     * @return bool
     */
    public function reorder($categoryIds) {
        $this->db->beginTransaction();

        try {
            foreach ($categoryIds as $order => $id) {
                $query = "UPDATE categories SET display_order = :order WHERE id = :id";
                $success = $this->db->execute($query, [
                    'order' => $order + 1,
                    'id' => $id
                ]);

                if (!$success) {
                    $this->db->rollback();
                    return false;
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error reordering categories: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Active ou désactive une catégorie
     * @param int $id
     * @param bool $isActive
     * @return bool
     */
    public function setActive($id, $isActive) {
        $query = "UPDATE categories SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        return $this->db->execute($query, [
            'id' => $id,
            'is_active' => $isActive ? 1 : 0
        ]);
    }
}
