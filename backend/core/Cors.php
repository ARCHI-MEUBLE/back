<?php
/**
 * ArchiMeuble - Gestion CORS centralisée
 * Gère les headers CORS pour tous les endpoints API
 * Date : 2025-10-22
 *
 * SÉCURITÉ: Liste blanche stricte des origines autorisées
 * Ne JAMAIS utiliser de wildcards pour les domaines en production
 */

class Cors {
    /**
     * Liste blanche des déploiements Vercel autorisés
     * IMPORTANT: Ajouter uniquement VOS déploiements spécifiques
     */
    private static $allowedVercelDeployments = [
        'https://archimeuble.vercel.app',
        'https://archimeuble-prod.vercel.app',
        'https://archimeuble-staging.vercel.app',
        // Ajouter d'autres déploiements spécifiques si nécessaire
    ];

    /**
     * Liste blanche des sous-domaines archimeuble.com autorisés
     */
    private static $allowedArchiSubdomains = [
        'https://archimeuble.com',
        'https://www.archimeuble.com',
        'https://app.archimeuble.com',
        'https://api.archimeuble.com',
        // Ajouter d'autres sous-domaines spécifiques si nécessaire
    ];

    /**
     * Configure les headers CORS automatiquement
     * Utilise une liste blanche stricte - AUCUN wildcard
     */
    public static function enable() {
        // Récupérer l'origine de la requête
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Liste des origines de développement autorisées (sans doublons)
        $allowedOrigins = [
            'http://127.0.0.1:3000',
            'http://127.0.0.1:3001',
            'http://localhost:3000',
            'http://localhost:3001',
        ];

        // Ajouter FRONTEND_URL depuis .env.local si défini
        $envFrontendUrl = getenv('FRONTEND_URL');
        if ($envFrontendUrl && !empty(trim($envFrontendUrl))) {
            $allowedOrigins[] = trim($envFrontendUrl);
        }

        // Fusionner avec les domaines de production autorisés
        $allowedOrigins = array_merge(
            $allowedOrigins,
            self::$allowedVercelDeployments,
            self::$allowedArchiSubdomains
        );

        // Supprimer les doublons
        $allowedOrigins = array_unique($allowedOrigins);

        // Vérifier si l'origine est dans la liste blanche (comparaison stricte)
        $isAllowed = in_array($origin, $allowedOrigins, true);

        // Log des tentatives d'accès non autorisées (en production)
        if (!$isAllowed && !empty($origin) && self::isProduction()) {
            error_log("[CORS BLOCKED] Origine non autorisée: " . $origin . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }

        if ($isAllowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
            // Credentials uniquement si origine autorisée
            header('Access-Control-Allow-Credentials: true');
        } else {
            // Ne PAS envoyer de header Allow-Origin pour les origines non autorisées
            // Cela bloquera la requête côté navigateur
            if (self::isProduction()) {
                // En production: bloquer silencieusement
                header('Access-Control-Allow-Origin: null');
            } else {
                // En développement: autoriser localhost par défaut
                header('Access-Control-Allow-Origin: http://127.0.0.1:3000');
                header('Access-Control-Allow-Credentials: true');
            }
        }

        // Headers de sécurité (toujours envoyés)
        self::setSecurityHeaders();

        // Headers CORS standards
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400'); // Cache preflight pendant 24h
        header('Content-Type: application/json; charset=utf-8');

        // Gérer les requêtes OPTIONS (preflight)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204); // No Content
            exit;
        }
    }

    /**
     * Ajoute les headers de sécurité HTTP recommandés
     */
    private static function setSecurityHeaders() {
        // Empêche le clickjacking
        header('X-Frame-Options: DENY');

        // Empêche le MIME sniffing
        header('X-Content-Type-Options: nosniff');

        // Protection XSS legacy (pour anciens navigateurs)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer Policy - limite les informations envoyées
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy - désactive les fonctionnalités non utilisées
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // HSTS - Force HTTPS (uniquement en production)
        if (self::isProduction()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Vérifie si on est en environnement de production
     */
    private static function isProduction(): bool {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return (
            strpos($host, 'localhost') === false &&
            strpos($host, '127.0.0.1') === false
        );
    }

    /**
     * Méthode pour ajouter dynamiquement une origine autorisée
     * Utile pour les tests ou configurations spécifiques
     */
    public static function addAllowedOrigin(string $origin): void {
        // Validation basique de l'URL
        if (filter_var($origin, FILTER_VALIDATE_URL)) {
            self::$allowedVercelDeployments[] = $origin;
        }
    }
}
