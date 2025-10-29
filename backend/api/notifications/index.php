<?php
/**
 * API: Gestion des notifications
 * GET /api/notifications - Lister les notifications
 * PUT /api/notifications/:id/read - Marquer comme lue
 * PUT /api/notifications/read-all - Marquer toutes comme lues
 * DELETE /api/notifications/:id - Supprimer une notification
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../models/Notification.php';

try {
    $notification = new Notification();
    $customerId = $_SESSION['customer_id'];

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Récupérer les notifications
            $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

            $notifications = $notification->getByCustomer($customerId, $unreadOnly, $limit);
            $unreadCount = $notification->countUnread($customerId);

            http_response_code(200);
            echo json_encode([
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
            break;

        case 'PUT':
            // Marquer comme lu ou tout marquer
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

            if (strpos($path, '/read-all') !== false) {
                // Marquer toutes comme lues
                $notification->markAllAsRead($customerId);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Toutes les notifications marquées comme lues'
                ]);
            } else if (preg_match('/\/(\d+)\/read$/', $path, $matches)) {
                // Marquer une notification comme lue
                $notificationId = (int)$matches[1];
                $notification->markAsRead($notificationId, $customerId);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification marquée comme lue'
                ]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Endpoint invalide']);
            }
            break;

        case 'DELETE':
            // Supprimer une notification
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de notification requis']);
                exit;
            }

            $notification->delete($_GET['id'], $customerId);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Notification supprimée'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
