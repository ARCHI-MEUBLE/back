<?php
/**
 * API Admin: Email Templates
 * GET /api/admin/email-templates - Liste tous les templates
 * PUT /api/admin/email-templates - Met à jour un template
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../models/EmailTemplate.php';

try {
    $emailTemplate = new EmailTemplate();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $templates = $emailTemplate->getAll();

        // Parser les gallery_images pour chaque template
        foreach ($templates as &$template) {
            $template['gallery_images_array'] = $emailTemplate->parseGalleryImages($template);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'templates' => $templates
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID du template manquant']);
            exit;
        }

        $emailTemplate->update($data['id'], $data);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Template mis à jour avec succès'
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
?>
