<?php
/**
 * API Admin Auth: Déconnexion admin
 * POST /backend/api/admin-auth/logout
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Nettoyer la session admin
if (isset($_SESSION['admin_email'])) {
    unset($_SESSION['admin_email']);
}

// Optionnel: détruire toute la session
// session_destroy();

http_response_code(200);
echo json_encode(['success' => true]);
