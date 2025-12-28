<?php
/**
 * Quote Request API
 * Handles customer quote requests with photo/video attachments
 */

header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Notification.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        // Validate required fields
        if (empty($_POST['first_name']) || empty($_POST['last_name']) ||
            empty($_POST['email']) || empty($_POST['phone'])) {
            throw new Exception('Tous les champs obligatoires doivent être remplis');
        }

        // Validate files
        if (empty($_FILES['files'])) {
            throw new Exception('Aucun fichier n\'a été envoyé');
        }

        // Start transaction
        $db->exec('BEGIN TRANSACTION');

        // Insert quote request
        $stmt = $db->prepare('
            INSERT INTO quote_requests (first_name, last_name, email, phone, description, status)
            VALUES (:first_name, :last_name, :email, :phone, :description, :status)
        ');

        $stmt->execute([
            ':first_name' => $_POST['first_name'],
            ':last_name' => $_POST['last_name'],
            ':email' => $_POST['email'],
            ':phone' => $_POST['phone'],
            ':description' => $_POST['description'] ?? '',
            ':status' => 'pending'
        ]);

        $quoteRequestId = $db->lastInsertId();

        // Handle file uploads
        $uploadDir = __DIR__ . '/../../uploads/quote-requests/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $files = $_FILES['files'];
        $fileCount = count($files['name']);
        $uploadedFiles = [];

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = $files['name'][$i];
                $fileTmpName = $files['tmp_name'][$i];
                $fileSize = $files['size'][$i];
                $fileType = $files['type'][$i];

                // Validate file type
                $isImage = strpos($fileType, 'image/') === 0;
                $isVideo = strpos($fileType, 'video/') === 0;

                if (!$isImage && !$isVideo) {
                    continue; // Skip invalid files
                }

                // Validate file size (10MB max)
                if ($fileSize > 10 * 1024 * 1024) {
                    continue; // Skip files too large
                }

                // Generate unique filename
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                $uniqueFileName = uniqid('quote_' . $quoteRequestId . '_', true) . '.' . $extension;
                $filePath = $uploadDir . $uniqueFileName;

                // Move uploaded file
                if (move_uploaded_file($fileTmpName, $filePath)) {
                    // Insert file record
                    $stmt = $db->prepare('
                        INSERT INTO quote_request_files
                        (quote_request_id, file_name, file_path, file_type, file_size)
                        VALUES (:quote_request_id, :file_name, :file_path, :file_type, :file_size)
                    ');

                    $stmt->execute([
                        ':quote_request_id' => $quoteRequestId,
                        ':file_name' => $fileName,
                        ':file_path' => $uniqueFileName,
                        ':file_type' => $isImage ? 'image' : 'video',
                        ':file_size' => $fileSize
                    ]);

                    $uploadedFiles[] = [
                        'name' => $fileName,
                        'type' => $isImage ? 'image' : 'video',
                        'size' => $fileSize
                    ];
                }
            }
        }

        // Create notification for admin
        $notification = new Notification($db);
        $notification->create(
            'new_quote_request',
            'Nouvelle demande de devis',
            sprintf(
                'Demande de devis de %s %s avec %d fichier(s)',
                $_POST['first_name'],
                $_POST['last_name'],
                count($uploadedFiles)
            ),
            null
        );

        // Commit transaction
        $db->exec('COMMIT');

        echo json_encode([
            'success' => true,
            'message' => 'Votre demande de devis a été envoyée avec succès',
            'data' => [
                'quote_request_id' => $quoteRequestId,
                'uploaded_files' => $uploadedFiles
            ]
        ]);

    } elseif ($method === 'GET') {
        // Get quote requests (admin only)
        // For now, just return a simple response
        // You can add admin authentication later

        $stmt = $db->query('
            SELECT qr.*, COUNT(qrf.id) as file_count
            FROM quote_requests qr
            LEFT JOIN quote_request_files qrf ON qr.id = qrf.quote_request_id
            GROUP BY qr.id
            ORDER BY qr.created_at DESC
            LIMIT 50
        ');

        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $requests
        ]);

    } else {
        throw new Exception('Méthode non autorisée');
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->exec('ROLLBACK');
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
