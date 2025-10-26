<?php
// Check admins in database
header('Content-Type: application/json');

try {
    // Try to connect to the database
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
        echo json_encode([
            'error' => 'Database not found',
            'path_checked' => $dbPath
        ]);
        exit;
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all admins
    $stmt = $pdo->query("SELECT id, username, email, created_at FROM admins");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get count
    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM admins");
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo json_encode([
        'success' => true,
        'db_path' => $dbPath,
        'admin_count' => $count,
        'admins' => $admins
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'db_path' => $dbPath ?? 'not set'
    ]);
}
