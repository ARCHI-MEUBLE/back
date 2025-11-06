<?php
/**
 * Configuration CORS pour toutes les API
 */

// Charger les variables d'environnement depuis .env (rechargement automatique)
require_once __DIR__ . '/env.php';

// Autoriser les requêtes depuis le frontend Next.js
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:3001',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:3001',
];

// Ajouter FRONTEND_URL depuis .env.local si défini
$envFrontendUrl = getenv('FRONTEND_URL');
if ($envFrontendUrl) {
    $allowedOrigins[] = $envFrontendUrl;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Vérifier si l'origine est autorisée
$isAllowed = in_array($origin, $allowedOrigins);

// Autoriser tous les domaines Vercel (.vercel.app)
if (!$isAllowed && preg_match('/^https:\/\/.*\.vercel\.app$/', $origin)) {
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
    // Optimisation: réduire la probabilité de garbage collection
    ini_set('session.gc_probability', 0);

    // Définir un chemin de session si non défini
    if (empty(ini_get('session.save_path'))) {
        ini_set('session.save_path', '/tmp');
    }

    // Utiliser des cookies sécurisés
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);

    session_start();
}
