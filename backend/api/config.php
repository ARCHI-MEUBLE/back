<?php
/**
 * API: Configuration publique
 * GET /api/config - Récupérer les configurations publiques (Calendly, Crisp)
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/env.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupérer les variables d'environnement depuis .env
    $calendlyPhoneUrl = getenv('CALENDLY_PHONE_URL') ?: '';
    $calendlyVisioUrl = getenv('CALENDLY_VISIO_URL') ?: '';
    $crispWebsiteId = getenv('CRISP_WEBSITE_ID') ?: '';

    http_response_code(200);
    echo json_encode([
        'calendly' => [
            'phoneUrl' => $calendlyPhoneUrl,
            'visioUrl' => $calendlyVisioUrl
        ],
        'crisp' => [
            'websiteId' => $crispWebsiteId
        ]
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
