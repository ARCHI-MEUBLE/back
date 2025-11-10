<?php
/**
 * ArchiMeuble - API Upload d'images
 * POST /api/upload - Upload une image pour un modèle
 */

require_once __DIR__ . '/../core/Cors.php';
require_once __DIR__ . '/../core/Session.php';

// Activer CORS
Cors::enable();

$session = Session::getInstance();

// Vérifier l'authentification admin
// Vérifier les deux méthodes d'auth: Session class OU $_SESSION natif
$isAdminSession = $session->has('is_admin') && $session->get('is_admin') === true;
$isAdminNative = isset($_SESSION['admin_email']) && !empty($_SESSION['admin_email']);

if (!$isAdminSession && !$isAdminNative) {
    error_log("[UPLOAD] Auth failed - is_admin: " . ($session->has('is_admin') ? 'yes' : 'no') .
              ", admin_email: " . ($_SESSION['admin_email'] ?? 'not set'));
    error_log("[UPLOAD] Session ID: " . session_id());
    error_log("[UPLOAD] Cookies: " . print_r($_COOKIE, true));
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'debug' => [
        'has_is_admin' => $session->has('is_admin'),
        'has_admin_email' => isset($_SESSION['admin_email']),
        'session_id' => session_id()
    ]]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    // Lire les données JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['fileName']) || !isset($data['fileType']) || !isset($data['data'])) {
        http_response_code(400);
        echo json_encode(['error' => 'File payload is incomplete']);
        exit;
    }

    $fileName = $data['fileName'];
    $fileType = $data['fileType'];
    $base64Data = $data['data'];

    // Vérifier le type de fichier
    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
    if (!in_array($fileType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported file type']);
        exit;
    }

    // Nettoyer le base64 (enlever le préfixe data:image/...)
    if (strpos($base64Data, ',') !== false) {
        $base64Data = explode(',', $base64Data)[1];
    }

    // Décoder le base64
    $imageData = base64_decode($base64Data);

    if ($imageData === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid base64 data']);
        exit;
    }

    // Générer un nom de fichier unique
    $extension = $fileType === 'image/png' ? 'png' : 'jpg';
    $uniqueName = time() . '-' . uniqid() . '.' . $extension;

    // Créer le dossier uploads dans le volume persistant /data
    $uploadDir = '/data/uploads/models/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fullPath = $uploadDir . $uniqueName;

    // Sauvegarder le fichier
    if (file_put_contents($fullPath, $imageData) === false) {
        throw new Exception('Failed to save file');
    }

    // Construire l'URL complète de l'image
    // Forcer HTTPS pour Railway/production, HTTP pour local
    $host = $_SERVER['HTTP_HOST'];
    $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
    $protocol = $isLocal ? 'http' : 'https';
    $baseUrl = $protocol . '://' . $host;

    $relativePath = '/uploads/models/' . $uniqueName;
    $fullUrl = $baseUrl . $relativePath;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'imagePath' => $fullUrl
    ]);

} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
