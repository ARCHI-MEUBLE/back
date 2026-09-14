<?php

declare(strict_types=1);

namespace App\Domain\EmailTemplate;

use App\Db\Connection;

final class EmailTemplateRepository
{
    public function __construct(private readonly Connection $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT * FROM email_templates ORDER BY template_name');
    }

    public function update(int $id, array $data): void
    {
        $columns = [];
        $params = [];
        foreach (self::mapping($data) as $column => $value) {
            $columns[] = $column . ' = ?';
            $params[] = $value;
        }
        $columns[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;
        $this->db->execute('UPDATE email_templates SET ' . implode(', ', $columns) . ' WHERE id = ?', $params);
    }

    public static function galleryImages(array $template): array
    {
        $raw = $template['gallery_images'] ?? null;
        if (!is_string($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function mapping(array $data): array
    {
        $columns = [];
        foreach (['subject', 'header_text', 'footer_text', 'custom_css'] as $field) {
            if (isset($data[$field])) {
                $columns[$field] = $data[$field];
            }
        }
        foreach (['show_logo', 'show_gallery'] as $field) {
            if (isset($data[$field])) {
                $columns[$field] = $data[$field] ? 1 : 0;
            }
        }
        if (isset($data['gallery_images'])) {
            $columns['gallery_images'] = is_array($data['gallery_images']) ? json_encode($data['gallery_images']) : $data['gallery_images'];
        }
        return $columns;
    }
}
