<?php
/**
 * ArchiMeuble - Router pour le serveur PHP
 * Gère le routing des requêtes vers les bons endpoints
 * Auteur : Collins
 * Date : 2025-10-20
 */

// Récupérer l'URI demandée
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Enlever les paramètres de query string
$path = parse_url($requestUri, PHP_URL_PATH);

// Router les requêtes API
if (preg_match('#^/backend/api/(.+)\.php$#', $path, $matches)) {
    // Requête vers l'API backend
    $apiFile = __DIR__ . '/backend/api/' . $matches[1] . '.php';

    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Endpoint non trouvé']);
        exit;
    }
}

// Pour les fichiers statiques, laisser PHP les servir
if (file_exists(__DIR__ . $path) && is_file(__DIR__ . $path)) {
    return false; // Laisser le serveur PHP intégré gérer le fichier
}

// Par défaut, servir index.php
require __DIR__ . '/index.php';
