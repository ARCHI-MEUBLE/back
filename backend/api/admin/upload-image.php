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

// Log de debug
error_log("UPLOAD: Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none'));
error_log("UPLOAD: Files array: " . print_r($_FILES, true));
error_log("UPLOAD: POST array: " . print_r($_POST, true));

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_email'])) {
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
    echo json_encode(['success' => false, 'error' => 'Aucune image envoyée', 'debug' => [
        'files' => $_FILES,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none'
    ]]);
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

// Déterminer le dossier d'upload sur le volume persistant Railway
$uploadDir = '/data/uploads/catalogue/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    error_log("UPLOAD: Created directory: $uploadDir");
}

// Générer un nom unique
$newFileName = uniqid('catalogue_', true) . '.' . $fileExt;
$destPath = $uploadDir . $newFileName;

error_log("UPLOAD: Attempting to save to: $destPath");

// Utiliser copy + unlink pour être plus robuste dans Docker avec les volumes
if (copy($fileTmpName, $destPath)) {
    unlink($fileTmpName);
    error_log("UPLOAD: Success! File saved to: $destPath");

    // Retourner l'URL relative au backend
    $fileUrl = '/uploads/catalogue/' . $newFileName;

    echo json_encode([
        'success' => true,
        'url' => $fileUrl,
        'filename' => $newFileName
    ]);
} else {
    $error = error_get_last();
    error_log("UPLOAD: Failed to copy file. Error: " . ($error['message'] ?? 'Unknown error'));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Impossible de sauvegarder le fichier sur le serveur. Problème de permissions ou de stockage.',
        'details' => $error['message'] ?? 'Unknown error'
    ]);
}
