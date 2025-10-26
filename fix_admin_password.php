<?php
// Fix admin password in production database
header('Content-Type: application/json');

try {
    $dbPath = getenv('DB_PATH');

    if (!$dbPath || empty($dbPath)) {
        $dbPath = '/data/database/archimeuble.db';
        if (!file_exists($dbPath)) {
            $dbPath = dirname(__DIR__) . '/database/archimeuble.db';
        }
    }

    // Normalize double slashes
    $dbPath = str_replace('//', '/', $dbPath);

    if (!file_exists($dbPath)) {
        echo json_encode(['error' => 'Database not found', 'path' => $dbPath]);
        exit;
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Generate correct hash for admin123
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Update the admin password
    $stmt = $pdo->prepare("UPDATE admins SET password = :password WHERE email = :email");
    $result = $stmt->execute([
        'password' => $hash,
        'email' => 'admin@archimeuble.com'
    ]);

    // Verify the update
    $stmt = $pdo->query("SELECT id, username, email FROM admins WHERE email = 'admin@archimeuble.com'");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Admin password updated to: admin123',
        'admin' => $admin,
        'hash_used' => $hash
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
