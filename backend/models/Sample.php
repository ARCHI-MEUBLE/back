<?php
/**
 * Modèle Sample (Échantillon)
 * Gère les types d'échantillons et leurs couleurs
 * Structure: sample_types (1) -> sample_colors (N)
 */

require_once __DIR__ . '/../core/Database.php';

class Sample {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // =====================================================
    // MÉTHODES POUR LES TYPES
    // =====================================================

    /**
     * Récupère tous les types d'échantillons
     */
    public function getAllTypes() {
        try {
            return $this->db->query("
                SELECT id, name, material, description, active, position, price_per_m2, unit_price, created_at, updated_at
                FROM sample_types
                ORDER BY material, position, name
            ");
        } catch (Exception $e) {
            error_log("Erreur Sample::getAllTypes: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération des types");
        }
    }

    /**
     * Récupère un type par ID
     */
    public function getTypeById($id) {
        try {
            $results = $this->db->query("
                SELECT id, name, material, description, active, position, price_per_m2, unit_price, created_at, updated_at
                FROM sample_types
                WHERE id = ?
            ", [$id]);
            return !empty($results) ? $results[0] : null;
        } catch (Exception $e) {
            error_log("Erreur Sample::getTypeById: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération du type");
        }
    }

    /**
     * Crée un nouveau type d'échantillon
     */
    public function createType($name, $material, $description = null, $position = 0) {
        try {
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare("
                INSERT INTO sample_types (name, material, description, position, active)
                VALUES (?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$name, $material, $description, (int)$position]);
            return $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur Sample::createType: " . $e->getMessage());
            throw new Exception("Erreur lors de la création du type");
        }
    }

    /**
     * Met à jour un type
     */
    public function updateType($id, $data) {
        try {
            $pdo = $this->db->getPDO();
            $type = $this->getTypeById($id);
            if (!$type) return false;

            $name = $data['name'] ?? $type['name'];
            $material = $data['material'] ?? $type['material'];
            $description = isset($data['description']) ? $data['description'] : $type['description'];
            $active = (isset($data['active']) && $data['active'] !== '') ? filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) : $type['active'];
            $position = isset($data['position']) ? (int)$data['position'] : $type['position'];

            $stmt = $pdo->prepare("
                UPDATE sample_types
                SET name = ?, material = ?, description = ?, active = ?, position = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            return $stmt->execute([$name, $material, $description, $active, $position, $id]);
        } catch (PDOException $e) {
            error_log("Erreur Sample::updateType: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un type (et toutes ses couleurs via CASCADE)
     */
    public function deleteType($id) {
        try {
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare("DELETE FROM sample_types WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Erreur Sample::deleteType: " . $e->getMessage());
            throw new Exception("Erreur lors de la suppression du type");
        }
    }

    // =====================================================
    // MÉTHODES POUR LES COULEURS
    // =====================================================

    /**
     * Récupère toutes les couleurs d'un type
     */
    public function getColorsByTypeId($type_id) {
        try {
            return $this->db->query("
                SELECT id, type_id, name, hex, image_url, active, position, price_per_m2, unit_price, created_at, updated_at
                FROM sample_colors
                WHERE type_id = ?
                ORDER BY position, name
            ", [$type_id]);
        } catch (Exception $e) {
            error_log("Erreur Sample::getColorsByTypeId: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération des couleurs");
        }
    }

    /**
     * Récupère une couleur par ID
     */
    public function getColorById($id) {
        try {
            $results = $this->db->query("
                SELECT id, type_id, name, hex, image_url, active, position, price_per_m2, unit_price, created_at, updated_at
                FROM sample_colors
                WHERE id = ?
            ", [$id]);
            return !empty($results) ? $results[0] : null;
        } catch (Exception $e) {
            error_log("Erreur Sample::getColorById: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération de la couleur");
        }
    }

    /**
     * Crée une nouvelle couleur pour un type
     */
    public function createColor($type_id, $name, $hex = null, $image_url = null, $position = 0, $price_per_m2 = 0, $unit_price = 0) {
        try {
            // Vérifier que le type existe
            if (!$this->getTypeById($type_id)) {
                throw new Exception("Type introuvable");
            }

            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare("
                INSERT INTO sample_colors (type_id, name, hex, image_url, position, active, price_per_m2, unit_price)
                VALUES (?, ?, ?, ?, ?, TRUE, ?, ?)
            ");
            $stmt->execute([$type_id, $name, $hex, $image_url, (int)$position, (float)$price_per_m2, (float)$unit_price]);
            return $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur Sample::createColor: " . $e->getMessage());
            throw new Exception("Erreur lors de la création de la couleur");
        }
    }

    /**
     * Met à jour une couleur
     */
    public function updateColor($id, $data) {
        try {
            $pdo = $this->db->getPDO();
            $color = $this->getColorById($id);
            if (!$color) return false;

            $name = $data['name'] ?? $color['name'];
            $hex = isset($data['hex']) ? $data['hex'] : $color['hex'];
            $image_url = isset($data['image_url']) ? $data['image_url'] : $color['image_url'];
            $active = (isset($data['active']) && $data['active'] !== '') ? filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) : $color['active'];
            $position = isset($data['position']) ? (int)$data['position'] : $color['position'];
            $price_per_m2 = isset($data['price_per_m2']) ? (float)$data['price_per_m2'] : $color['price_per_m2'];
            $unit_price = isset($data['unit_price']) ? (float)$data['unit_price'] : $color['unit_price'];

            $stmt = $pdo->prepare("
                UPDATE sample_colors
                SET name = ?, hex = ?, image_url = ?, active = ?, position = ?,
                    price_per_m2 = ?, unit_price = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            return $stmt->execute([$name, $hex, $image_url, $active, $position, $price_per_m2, $unit_price, $id]);
        } catch (PDOException $e) {
            error_log("Erreur Sample::updateColor: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime une couleur
     */
    public function deleteColor($id) {
        try {
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare("DELETE FROM sample_colors WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Erreur Sample::deleteColor: " . $e->getMessage());
            throw new Exception("Erreur lors de la suppression de la couleur");
        }
    }

    // =====================================================
    // MÉTHODES POUR API (DONNÉES GROUPÉES)
    // =====================================================

    /**
     * Récupère tous les types avec leurs couleurs (pour admin)
     * Retourne: [{ id, name, material, description, active, position, colors: [...] }]
     */
    public function getAllGroupedForAdmin() {
        try {
            $types = $this->getAllTypes();
            $result = [];

            foreach ($types as $type) {
                $colors = $this->getColorsByTypeId($type['id']);
                $result[] = [
                    'id' => $type['id'],
                    'name' => $type['name'],
                    'material' => $type['material'],
                    'description' => $type['description'],
                    'active' => $type['active'],
                    'position' => $type['position'],
                    'colors' => $colors
                ];
            }

            return $result;
        } catch (Exception $e) {
            error_log("Erreur Sample::getAllGroupedForAdmin: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupère les échantillons groupés par matériau (pour API publique)
     * Retourne: { "Matériau1": [{ id, name, material, colors: [...] }], ... }
     */
    public function getGroupedByMaterial() {
        try {
            $types = $this->getAllTypes();
            $grouped = [];

            foreach ($types as $type) {
                // Ne retourner que les types actifs pour l'API publique
                if (!$type['active']) continue;

                $material = $type['material'];
                if (!isset($grouped[$material])) {
                    $grouped[$material] = [];
                }

                // Récupérer les couleurs actives uniquement
                $allColors = $this->getColorsByTypeId($type['id']);
                $activeColors = array_filter($allColors, function($c) {
                    return $c['active'];
                });

                $grouped[$material][] = [
                    'id' => $type['id'],
                    'name' => $type['name'],
                    'material' => $type['material'],
                    'description' => $type['description'],
                    'active' => $type['active'],
                    'position' => $type['position'],
                    'colors' => array_values($activeColors)
                ];
            }

            return $grouped;
        } catch (Exception $e) {
            error_log("Erreur Sample::getGroupedByMaterial: " . $e->getMessage());
            throw $e;
        }
    }
}
