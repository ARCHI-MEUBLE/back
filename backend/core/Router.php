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

        // Route de diagnostic admins
        if ($path === 'check-admins') {
            require $this->baseDir . '/check_admins.php';
            return;
        }

        // Route pour corriger le mot de passe admin
        if ($path === 'fix-admin-password') {
            require $this->baseDir . '/fix_admin_password.php';
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

        // Essayer d'abord le chemin exact (ex: admin/samples -> backend/api/admin/samples.php)
        $exactFile = $this->baseDir . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . $endpoint . '.php';
        $exactFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $exactFile);

        if (file_exists($exactFile)) {
            require $exactFile;
            return;
        }

        // Sinon, fallback sur le fichier racine (ex: admin -> backend/api/admin.php)
        $parts = explode('/', $endpoint);
        $mainEndpoint = $parts[0];

        $apiFile = $this->baseDir . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . $mainEndpoint . '.php';
        $apiFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $apiFile);

        if (file_exists($apiFile)) {
            require $apiFile;
            return;
        }

        // Debug si rien trouvé
        $this->sendJSON([
            'success' => false,
            'error' => 'Endpoint non trouvé',
            'requested' => $endpoint,
            'tried_exact' => $exactFile,
            'tried_root' => $apiFile
        ], 404);
    }

    /**
     * Gérer les routes backend API (/backend/api/...)
     * @param string $path
     */
    private function handleBackendAPI($path) {
        // Extraire l'endpoint depuis backend/api/...
        $endpoint = str_replace('backend/api/', '', $path);
        $endpoint = explode('?', $endpoint)[0]; // Enlever les query params

        // Enlever l'extension .php si présente (même si suivie d'un /)
        $endpoint = preg_replace('/\.php(\/|$)/', '$1', $endpoint);

        // Essayer d'abord le chemin exact (pour les fichiers dans des sous-dossiers)
        $exactFile = $this->baseDir . '/backend/api/' . $endpoint . '.php';

        if (file_exists($exactFile)) {
            require $exactFile;
            return;
        }

        // Sinon, gérer les sous-routes avec fichier principal (ex: admin-auth/login -> admin-auth.php)
        // Extraire la première partie (ex: admin-auth, admin, customers, facade-materials)
        $parts = explode('/', $endpoint);
        $mainEndpoint = $parts[0];
        
        // Le reste du path devient PATH_INFO pour l'API
        $pathInfo = '';
        if (count($parts) > 1) {
            $pathInfo = '/' . implode('/', array_slice($parts, 1));
        }

        // Construire le chemin du fichier API principal
        $apiFile = $this->baseDir . '/backend/api/' . $mainEndpoint . '.php';

        if (file_exists($apiFile)) {
            // Définir PATH_INFO pour que l'API puisse extraire l'ID
            $_SERVER['PATH_INFO'] = $pathInfo;
            require $apiFile;
        } else {
            $this->sendJSON([
                'success' => false,
                'error' => 'Endpoint non trouvé',
                'requested' => $endpoint
            ], 404);
        }
    }

    /**
     * Vérifier si c'est un fichier statique
     * @param string $path
     * @return bool
     */
    private function isStaticFile($path) {
        $extensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'glb', 'gltf', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'dxf'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $extensions);
    }

    /**
     * Servir un fichier statique
     * @param string $path
     */
    private function serveStaticFile($path) {
        // Normaliser le path (retirer le préfixe backend/ si présent)
        $cleanPath = $path;
        if (strpos($path, 'backend/uploads/') === 0) {
            $cleanPath = substr($path, 8); // Retire "backend/"
        }

        // Chemins possibles à essayer (production puis local)
        $possiblePaths = [];

        if (strpos($cleanPath, 'uploads/') === 0) {
            // Production: /data/uploads/...
            $possiblePaths[] = '/data/' . $cleanPath;
            // Local dev: baseDir/backend/uploads/...
            $possiblePaths[] = $this->baseDir . '/backend/' . $cleanPath;
        } elseif (strpos($cleanPath, 'models/') === 0) {
            // Production: /data/models/...
            $possiblePaths[] = '/data/' . $cleanPath;
            // Local: baseDir/models/...
            $possiblePaths[] = $this->baseDir . '/' . $cleanPath;
        } elseif (strpos($cleanPath, 'back/textures/') === 0) {
            // Textures: back/textures/... -> textures/...
            $texturePath = substr($cleanPath, strlen('back/'));
            $possiblePaths[] = $this->baseDir . '/' . $texturePath;
        } elseif (strpos($path, 'back/textures/') === 0) {
            // Cas où le path original a back/textures/
            $texturePath = substr($path, strlen('back/'));
            $possiblePaths[] = $this->baseDir . '/' . $texturePath;
        } else {
            $possiblePaths[] = $this->baseDir . '/' . $path;
        }

        // Trouver le premier chemin qui existe
        $filePath = null;
        foreach ($possiblePaths as $tryPath) {
            if (file_exists($tryPath) && is_file($tryPath)) {
                $filePath = $tryPath;
                break;
            }
        }

        // Si aucun fichier trouvé, utiliser le premier chemin pour le message d'erreur
        if ($filePath === null) {
            $filePath = $possiblePaths[0];
        }

        error_log("STATIC FILE: Requested path: $path");
        error_log("STATIC FILE: Resolved to: $filePath");
        error_log("STATIC FILE: File exists: " . (file_exists($filePath) ? 'YES' : 'NO'));

        if (file_exists($filePath)) {
            error_log("STATIC FILE: Is file: " . (is_file($filePath) ? 'YES' : 'NO'));
            if (is_file($filePath)) {
                error_log("STATIC FILE: File size: " . filesize($filePath) . " bytes");
            }
        } else {
            // Si le fichier n'existe pas, lister le contenu du dossier parent pour debug
            $parentDir = dirname($filePath);
            if (is_dir($parentDir)) {
                $files = scandir($parentDir);
                error_log("STATIC FILE: Parent dir contents: " . implode(", ", $files));
            } else {
                error_log("STATIC FILE: Parent dir does not exist: $parentDir");
            }
        }

        if (file_exists($filePath) && is_file($filePath)) {
            $contentType = $this->getContentType($path);

            // Headers CORS dynamiques pour tous les fichiers statiques
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowedOrigins = [
                'http://127.0.0.1:3000',
                'http://localhost:3001',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:3001',
            ];

            // Ajouter FRONTEND_URL depuis .env.local
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
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'glb' => 'model/gltf-binary',
            'gltf' => 'model/gltf+json',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'dxf' => 'application/dxf'
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

        // Gérer CORS correctement avec credentials
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = [
            'http://127.0.0.1:3000',
            'http://localhost:3001',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:3001',
        ];

        $envFrontendUrl = getenv('FRONTEND_URL');
        if ($envFrontendUrl) {
            $allowedOrigins[] = $envFrontendUrl;
        }

        $isAllowed = in_array($origin, $allowedOrigins);
        if (!$isAllowed && preg_match('/^https:\/\/.*\.vercel\.app$/', $origin)) {
            $isAllowed = true;
        }

        if ($isAllowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            header('Access-Control-Allow-Origin: http://127.0.0.1:3000');
        }

        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, Cookie, Set-Cookie');

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
