<?php
/**
 * API: Upload d'images pour les modèles
 * POST /api/admin/upload-image.php
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune image envoyée']);
    exit;
}

$file = $_FILES['image'];
$fileName = $file['name'];
$fileTmpName = $file['tmp_name'];
$fileSize = $file['size'];
$fileError = $file['error'];

// Log des infos reçues pour debug
error_log("File received: " . $fileName . " Size: " . $fileSize . " Error: " . $fileError);

if ($fileError !== 0) {
    $errorMsg = 'Erreur lors du transfert du fichier (code ' . $fileError . ')';
    if ($fileError === 1 || $fileError === 2) $errorMsg = 'Le fichier est trop volumineux pour le serveur (max 20Mo)';
    http_response_code(500);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

// Vérifier l'extension
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp'];

if (!in_array($fileExt, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Format de fichier non autorisé (JPG, PNG, WEBP uniquement)']);
    exit;
}

// Vérifier la taille (max 20Mo)
if ($fileSize > 20 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Fichier trop volumineux (max 20Mo)']);
    exit;
}

// Déterminer le dossier d'upload (utiliser le volume partagé /app/models)
$isDocker = file_exists('/app');
$uploadDir = $isDocker ? '/app/models/photos/' : __DIR__ . '/../../../../front/public/models/photos/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Générer un nom unique
$newFileName = uniqid('model_', true) . '.' . $fileExt;
$destPath = $uploadDir . $newFileName;

// Utiliser copy + unlink pour être plus robuste dans Docker avec les volumes
if (copy($fileTmpName, $destPath)) {
    unlink($fileTmpName);
    error_log("Upload success via copy: $destPath");
    echo json_encode([
        'success' => true,
        'url' => '/models/photos/' . $newFileName
    ]);
} else {
    $error = error_get_last();
    error_log("Upload failed (copy): " . ($error['message'] ?? 'Unknown error') . " Dest: $destPath");
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de sauvegarder le fichier sur le serveur. Problème de permissions ou de stockage.']);
}
