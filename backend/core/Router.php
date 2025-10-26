<?php
/**
 * ArchiMeuble - Classe Router
 * Gère le routing des URLs vers les bonnes pages/API
 * Auteur : Ilyes
 * Date : 2025-10-20
 */

class Router {
    private $baseDir;

    public function __construct() {
        // Remonte de 2 niveaux depuis backend/core/ vers la racine
        $this->baseDir = dirname(dirname(__DIR__));
    }

    /**
     * Route une URI vers la bonne destination
     * @param string $requestUri
     */
    public function route($requestUri) {
        // Log de la requête
        error_log("[" . date('Y-m-d H:i:s') . "] " . $_SERVER['REQUEST_METHOD'] . " " . $requestUri);

        // Enlever les paramètres de query string
        $path = parse_url($requestUri, PHP_URL_PATH);

        // Nettoyer le path
        $path = trim($path, '/');

        // Route : Health check / API root
        if ($path === '' || $path === 'health') {
            $this->sendJSON([
                'success' => true,
                'service' => 'ArchiMeuble Backend API',
                'version' => '1.0.0',
                'status' => 'healthy',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            return;
        }

        // Route temporaire : Créer un admin (à supprimer après utilisation)
        if ($path === 'create-admin-temp') {
            require $this->baseDir . '/create_admin_temp.php';
            return;
        }

        // Route temporaire : Supprimer les modèles par défaut
        if ($path === 'delete-default-models') {
            require $this->baseDir . '/delete_default_models.php';
            return;
        }

        // Route de diagnostic
        if ($path === 'debug-db') {
            header('Content-Type: text/plain');
            require $this->baseDir . '/debug_db_path.php';
            return;
        }

        // Route : Configurateur
        if ($path === 'configurator' || $path === 'configurator.html') {
            $this->serveFrontendPage('configurator.html');
            return;
        }

        // Route : Test viewer
        if ($path === 'test-viewer.html' || $path === 'test-viewer') {
            $this->serveFrontendPage('test-viewer.html');
            return;
        }

        // Routes API
        if (strpos($path, 'api/') === 0) {
            $this->handleAPI($path);
            return;
        }

        // Routes backend API (pour compatibilité avec Collins)
        if (strpos($path, 'backend/api/') === 0) {
            $this->handleBackendAPI($path);
            return;
        }

        // Fichiers statiques
        if ($this->isStaticFile($path)) {
            $this->serveStaticFile($path);
            return;
        }

        // 404 - Page non trouvée
        $this->send404();
    }

    /**
     * Servir une page HTML du frontend
     * @param string $page
     */
    private function serveFrontendPage($page) {
        $filePath = $this->baseDir . '/frontend/pages/' . $page;

        if (file_exists($filePath)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($filePath);
        } else {
            $this->send404("Page non trouvée : $page");
        }
    }

    /**
     * Gérer les routes API (/api/...)
     * @param string $path
     */
    private function handleAPI($path) {
        // Extraire le nom de l'endpoint
        $endpoint = str_replace('api/', '', $path);
        $endpoint = explode('?', $endpoint)[0]; // Enlever les query params

        // Gérer les sous-routes (ex: auth/login, admin-auth/logout)
        // Extraire la première partie (ex: auth, admin-auth, models)
        $parts = explode('/', $endpoint);
        $mainEndpoint = $parts[0];

        // Normaliser les slashes pour Windows
        $apiFile = $this->baseDir . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . $mainEndpoint . '.php';
        $apiFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $apiFile);

        if (file_exists($apiFile)) {
            require $apiFile;
        } else {
            // Debug : afficher le chemin cherché
            $this->sendJSON([
                'success' => false,
                'error' => 'Endpoint non trouvé',
                'debug_path' => $apiFile,
                'debug_endpoint' => $endpoint,
                'debug_main_endpoint' => $mainEndpoint,
                'debug_exists' => file_exists($apiFile) ? 'yes' : 'no'
            ], 404);
        }
    }

    /**
     * Gérer les routes backend API (/backend/api/...)
     * @param string $path
     */
    private function handleBackendAPI($path) {
        $apiFile = $this->baseDir . '/' . $path;

        if (file_exists($apiFile)) {
            require $apiFile;
        } else {
            $this->sendJSON(['success' => false, 'error' => 'Endpoint non trouvé'], 404);
        }
    }

    /**
     * Vérifier si c'est un fichier statique
     * @param string $path
     * @return bool
     */
    private function isStaticFile($path) {
        $extensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'glb', 'gltf', 'ico', 'woff', 'woff2', 'ttf', 'eot'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $extensions);
    }

    /**
     * Servir un fichier statique
     * @param string $path
     */
    private function serveStaticFile($path) {
        // Les fichiers uploads sont dans le volume persistant /data
        if (strpos($path, 'uploads/') === 0) {
            $filePath = '/data/' . $path;
        } else {
            $filePath = $this->baseDir . '/' . $path;
        }

        if (file_exists($filePath) && is_file($filePath)) {
            $contentType = $this->getContentType($path);

            // Headers CORS dynamiques pour tous les fichiers statiques
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowedOrigins = [
                'http://localhost:3000',
                'http://localhost:3001',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:3001',
            ];

            // Ajouter FRONTEND_URL depuis .env
            $envFrontendUrl = getenv('FRONTEND_URL');
            if ($envFrontendUrl) {
                $allowedOrigins[] = $envFrontendUrl;
            }

            // Vérifier si l'origine est autorisée ou si c'est un domaine Vercel
            $isAllowed = in_array($origin, $allowedOrigins);
            if (!$isAllowed && preg_match('/^https:\/\/.*\.vercel\.app$/', $origin)) {
                $isAllowed = true;
            }

            if ($isAllowed) {
                header('Access-Control-Allow-Origin: ' . $origin);
            } else {
                header('Access-Control-Allow-Origin: *'); // Fallback: autoriser tout le monde pour les fichiers statiques
            }

            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            header('Content-Type: ' . $contentType);

            // Gérer OPTIONS preflight
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(204);
                exit;
            }

            readfile($filePath);
        } else {
            // Même pour un 404, envoyer les headers CORS
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if (preg_match('/^https:\/\/.*\.vercel\.app$/', $origin)) {
                header('Access-Control-Allow-Origin: ' . $origin);
            } else {
                header('Access-Control-Allow-Origin: *');
            }

            $this->send404("Fichier non trouvé : $path");
        }
    }

    /**
     * Déterminer le Content-Type d'un fichier
     * @param string $path
     * @return string
     */
    private function getContentType($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'glb' => 'model/gltf-binary',
            'gltf' => 'model/gltf+json',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];

        return isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
    }

    /**
     * Envoyer une réponse JSON
     * @param array $data
     * @param int $statusCode
     */
    private function sendJSON($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($data);
    }

    /**
     * Envoyer une erreur 404
     * @param string $message
     */
    private function send404($message = 'Page non trouvée') {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>404 - Page non trouvée</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background-color: #f4f4f4;
        }
        h1 { color: #e74c3c; font-size: 72px; margin: 0; }
        p { font-size: 24px; color: #555; }
    </style>
</head>
<body>
    <h1>404</h1>
    <p>$message</p>
    <a href='/'>Retour à l'accueil</a>
</body>
</html>";
    }
}
