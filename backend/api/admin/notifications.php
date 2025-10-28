<?php
/**
 * API Admin: Notifications
 * GET /api/admin/notifications - Lister les notifications
 * PUT /api/admin/notifications - Marquer comme lu
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

require_once __DIR__ . '/../../models/AdminNotification.php';

try {
    $notification = new AdminNotification();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';
        
        if ($unreadOnly) {
            $notifications = $notification->getUnread(50);
        } else {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $notifications = $notification->getAll($limit, $offset);
        }
        
        $unreadCount = $notification->countUnread();
        
        http_response_code(200);
        echo json_encode([
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);

        // Supporter à la fois 'mark_all_read' (backend) et 'mark_all_as_read' (frontend)
        $markAllRead = false;
        if (isset($data['mark_all_read'])) {
            $markAllRead = (bool)$data['mark_all_read'];
        } elseif (isset($data['mark_all_as_read'])) {
            $markAllRead = (bool)$data['mark_all_as_read'];
        }

        if ($markAllRead) {
            $notification->markAllAsRead();
        } elseif (isset($data['notification_id'])) {
            $notification->markAsRead($data['notification_id']);
        } elseif (isset($data['mark_as_read']) && isset($data['id'])) {
            // Variante: { id, mark_as_read: true }
            $notification->markAsRead($data['id']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Paramètre invalide']);
            exit;
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Notification(s) marquée(s) comme lue(s)'
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
