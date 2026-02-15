<?php
/**
 * API Admin: Inscription desactivee
 * La creation d'admin se fait uniquement via la base de donnees (Railway).
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

http_response_code(403);
echo json_encode(['error' => 'Inscription desactivee']);
