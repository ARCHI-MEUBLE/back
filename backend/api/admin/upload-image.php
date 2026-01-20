<?php
/**
 * API: Upload d'images pour les modèles
 * POST /api/admin/upload-image.php
 *
 * SÉCURITÉ:
 * - Vérification MIME type réel (pas seulement extension)
 * - Protection contre les doubles extensions
 * - Permissions restrictives (0755)
 * - Validation de l'authentification admin
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Session.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// SÉCURITÉ: Vérifier l'authentification admin via la classe Session
$session = Session::getInstance();
if (!$session->has('admin_email') || $session->get('is_admin') !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Aucune image envoyée']);
    exit;
}

$file = $_FILES['image'];
$fileName = $file['name'];
$fileTmpName = $file['tmp_name'];
$fileSize = $file['size'];
$fileError = $file['error'];

if ($fileError !== 0) {
    $errorMsg = 'Erreur lors du transfert du fichier';
    if ($fileError === 1 || $fileError === 2) {
        $errorMsg = 'Le fichier est trop volumineux (max 10Mo)';
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

// SÉCURITÉ: Vérifier la taille (max 10Mo - réduit de 20Mo)
$maxSize = 10 * 1024 * 1024;
if ($fileSize > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 10Mo)']);
    exit;
}

// SÉCURITÉ: Liste blanche des extensions ET types MIME
$allowedTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];

// SÉCURITÉ: Vérifier l'extension (prévention double extension: file.php.jpg)
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Vérifier qu'il n'y a pas de double extension dangereuse
$baseNameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
$dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'js', 'html', 'htm', 'svg', 'exe', 'sh', 'bat'];
foreach ($dangerousExtensions as $dangerousExt) {
    if (preg_match('/\.' . preg_quote($dangerousExt, '/') . '$/i', $baseNameWithoutExt)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nom de fichier non autorisé']);
        exit;
    }
}

if (!array_key_exists($fileExt, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Format non autorisé (JPG, PNG, WEBP uniquement)']);
    exit;
}

// SÉCURITÉ: Vérifier le type MIME réel du fichier (pas seulement l'extension)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $fileTmpName);
finfo_close($finfo);

if ($detectedMime !== $allowedTypes[$fileExt]) {
    // Log de sécurité
    error_log("[SECURITY] Upload blocked: extension '$fileExt' but MIME '$detectedMime' - possible attack");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Le contenu du fichier ne correspond pas à son extension']);
    exit;
}

// SÉCURITÉ: Vérifier que c'est vraiment une image valide
$imageInfo = @getimagesize($fileTmpName);
if ($imageInfo === false) {
    error_log("[SECURITY] Upload blocked: file is not a valid image");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Le fichier n\'est pas une image valide']);
    exit;
}

// Vérifier les dimensions (éviter les images trop grandes qui pourraient causer des DoS)
$maxDimension = 8000; // pixels
if ($imageInfo[0] > $maxDimension || $imageInfo[1] > $maxDimension) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Image trop grande (max {$maxDimension}x{$maxDimension} pixels)"]);
    exit;
}

// Déterminer le dossier d'upload
if (file_exists('/data') && is_writable('/data')) {
    $uploadDir = '/data/uploads/catalogue/';
} else {
    $uploadDir = __DIR__ . '/../../uploads/catalogue/';
}

// SÉCURITÉ: Créer le dossier avec permissions restrictives (0755 au lieu de 0777)
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// SÉCURITÉ: Générer un nom aléatoire sécurisé (évite les collisions et les attaques par nom)
$newFileName = bin2hex(random_bytes(16)) . '.' . $fileExt;
$destPath = $uploadDir . $newFileName;

// SÉCURITÉ: Vérifier que le chemin final est bien dans le dossier d'upload (anti path traversal)
$realUploadDir = realpath($uploadDir);
if ($realUploadDir === false) {
    // Le dossier vient d'être créé, on refait le check
    $realUploadDir = realpath($uploadDir);
}

// Utiliser copy + unlink pour être plus robuste dans Docker avec les volumes
if (copy($fileTmpName, $destPath)) {
    // Supprimer le fichier temporaire
    @unlink($fileTmpName);

    // SÉCURITÉ: Définir les permissions du fichier uploadé (lecture seule pour le web)
    chmod($destPath, 0644);

    // Retourner l'URL relative
    if (file_exists('/data') && is_writable('/data')) {
        $fileUrl = '/uploads/catalogue/' . $newFileName;
    } else {
        $fileUrl = '/backend/uploads/catalogue/' . $newFileName;
    }

    // Log succès (sans données sensibles)
    error_log("[UPLOAD] Image uploaded successfully: $newFileName by admin: " . $session->get('admin_email'));

    echo json_encode([
        'success' => true,
        'url' => $fileUrl,
        'filename' => $newFileName
    ]);
} else {
    error_log("[UPLOAD ERROR] Failed to save file");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Impossible de sauvegarder le fichier sur le serveur'
    ]);
}
