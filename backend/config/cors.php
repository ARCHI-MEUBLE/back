<?php
/**
 * Configuration CORS pour toutes les API
 */

// Autoriser les requêtes depuis le frontend Next.js
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:3001',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:3001',
];

// Ajouter FRONTEND_URL depuis .env si défini
$envFrontendUrl = getenv('FRONTEND_URL');
if ($envFrontendUrl) {
    $allowedOrigins[] = $envFrontendUrl;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Vérifier si l'origine est autorisée
$isAllowed = in_array($origin, $allowedOrigins);

// Autoriser tous les domaines Vercel (.vercel.app) et Railway (.railway.app)
if (!$isAllowed && preg_match('/^https:\/\/.*\.(vercel|railway)\.app$/', $origin)) {
    $isAllowed = true;
}

if ($isAllowed) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: http://localhost:3000');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Cookie, Set-Cookie');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

// Répondre aux requêtes preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Démarrer la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
