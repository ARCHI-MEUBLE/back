<?php
/**
 * Endpoint protege pour creer un admin
 * POST /api/system/create-admin?key=SECRET
 * Body: {"email":"...", "password":"...", "username":"..."}
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verification cle API
$apiKey = $_GET['key'] ?? '';
$validKey = getenv('BACKUP_API_KEY') ?: 'archimeuble-bkp-K9x4mQ7rT2wZ8pL5';

if ($apiKey !== $validKey) {
    http_response_code(403);
    echo json_encode(['error' => 'Cle API invalide']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST uniquement']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? null;
$password = $input['password'] ?? null;
$username = $input['username'] ?? null;

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'email et password requis']);
    exit;
}

if (!$username) {
    $username = explode('@', $email)[0];
}

$hash = password_hash($password, PASSWORD_BCRYPT);

require_once __DIR__ . '/../../core/Database.php';
$db = Database::getInstance();

// Verifier si l'email existe deja
$existing = $db->queryOne("SELECT id FROM admins WHERE email = ?", [$email]);
if ($existing) {
    // Mettre a jour le mot de passe
    $db->execute("UPDATE admins SET password = ? WHERE email = ?", [$hash, $email]);
    echo json_encode(['success' => true, 'action' => 'updated', 'email' => $email]);
} else {
    // Creer l'admin
    $db->execute("INSERT INTO admins (username, email, password, created_at) VALUES (?, ?, ?, NOW())", [$username, $email, $hash]);
    echo json_encode(['success' => true, 'action' => 'created', 'email' => $email]);
}
