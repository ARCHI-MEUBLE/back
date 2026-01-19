<?php
/**
 * Upload de fichiers de texture pour matériaux de façades
 * Endpoint: /backend/api/upload-texture.php
 * Méthode: POST (multipart/form-data)
 * Champ fichier attendu: "file"
 * Retour: { success: true, url: "/back/textures/<nom>" }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu']);
    exit();
}

$file = $_FILES['file'];

// Validation basique
$allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
if (!in_array($file['type'], $allowed)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Type de fichier non supporté']);
    exit();
}

// Répertoire de destination: back/textures
$destDir = __DIR__ . '/../../textures/';
if (!is_dir($destDir)) {
    @mkdir($destDir, 0777, true);
}

// Générer un nom de fichier unique
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$basename = 'texture_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$filename = $basename . '.' . strtolower($ext);
$destPath = $destDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Échec de l'upload"]);
    exit();
}

// URL publique relative (accessible par le front)
$publicUrl = '/back/textures/' . $filename;

echo json_encode(['success' => true, 'url' => $publicUrl]);
exit();

?>