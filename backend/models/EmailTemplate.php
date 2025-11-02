<?php
/**
 * Modèle EmailTemplate - Gestion des templates d'emails personnalisables
 */

require_once __DIR__ . '/../core/Database.php';

class EmailTemplate {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensureTableExists();
    }

    /**
     * S'assure que la table existe (auto-migration)
     */
    private function ensureTableExists() {
        $sql = file_get_contents(__DIR__ . '/../config/email_templates.sql');
        if ($sql) {
            $this->db->execute($sql);
        }
    }

    /**
     * Récupère un template par son nom
     */
    public function getByName($templateName) {
        $sql = "SELECT * FROM email_templates WHERE template_name = ? AND is_active = 1";
        return $this->db->queryOne($sql, [$templateName]);
    }

    /**
     * Récupère tous les templates
     */
    public function getAll() {
        $sql = "SELECT * FROM email_templates ORDER BY template_name";
        return $this->db->query($sql);
    }

    /**
     * Met à jour un template
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];

        if (isset($data['subject'])) {
            $fields[] = 'subject = ?';
            $params[] = $data['subject'];
        }
        if (isset($data['header_text'])) {
            $fields[] = 'header_text = ?';
            $params[] = $data['header_text'];
        }
        if (isset($data['footer_text'])) {
            $fields[] = 'footer_text = ?';
            $params[] = $data['footer_text'];
        }
        if (isset($data['show_logo'])) {
            $fields[] = 'show_logo = ?';
            $params[] = $data['show_logo'] ? 1 : 0;
        }
        if (isset($data['show_gallery'])) {
            $fields[] = 'show_gallery = ?';
            $params[] = $data['show_gallery'] ? 1 : 0;
        }
        if (isset($data['gallery_images'])) {
            $fields[] = 'gallery_images = ?';
            $params[] = is_array($data['gallery_images']) ? json_encode($data['gallery_images']) : $data['gallery_images'];
        }
        if (isset($data['custom_css'])) {
            $fields[] = 'custom_css = ?';
            $params[] = $data['custom_css'];
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;

        $sql = "UPDATE email_templates SET " . implode(', ', $fields) . " WHERE id = ?";
        return $this->db->execute($sql, $params);
    }

    /**
     * Parse les images de la galerie (JSON -> array)
     */
    public function parseGalleryImages($template) {
        if (!$template || !isset($template['gallery_images'])) {
            return [];
        }
        return json_decode($template['gallery_images'], true) ?: [];
    }
}
?>
