<?php
/**
 * ArchiMeuble - Gestion CORS centralisée
 * Gère les headers CORS pour tous les endpoints API
 * Date : 2025-10-22
 */

class Cors {
    /**
     * Configure les headers CORS automatiquement
     * Utilise FRONTEND_URL depuis .env ou autorise localhost:3000/3001 par défaut
     */
    public static function enable() {
        // Récupérer l'origine de la requête
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Liste des origines autorisées
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

        // Vérifier si l'origine est autorisée
        $isAllowed = in_array($origin, $allowedOrigins);

        // Autoriser tous les domaines Vercel (.vercel.app)
        if (!$isAllowed && preg_match('/^https:\/\/.*\.vercel\.app$/', $origin)) {
            $isAllowed = true;
        }

        if ($isAllowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            // En développement, autoriser localhost par défaut
            header('Access-Control-Allow-Origin: http://localhost:3000');
        }

        // Autres headers CORS
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, Cookie, Set-Cookie');
        header('Access-Control-Max-Age: 86400'); // Cache preflight pendant 24h
        header('Content-Type: application/json; charset=utf-8');

        // Gérer les requêtes OPTIONS (preflight)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204); // No Content
            exit;
        }
    }
}
