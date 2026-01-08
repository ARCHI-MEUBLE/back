<?php
/**
 * API Admin: Gérer les commandes
 * GET /api/admin/orders - Lister toutes les commandes
 * PUT /api/admin/orders - Mettre à jour le statut
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier l'authentification admin
$session = Session::getInstance();
if (!$session->has('admin_email') || $session->get('is_admin') !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../models/Order.php';
require_once __DIR__ . '/../../models/AdminNotification.php';
require_once __DIR__ . '/../../models/Notification.php';
require_once __DIR__ . '/../../core/Database.php';

try {
    $order = new Order();
    $db = Database::getInstance();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Lister les commandes
        if (isset($_GET['id'])) {
            // Détail d'une commande
            $orderData = $order->getById($_GET['id']);

            if (!$orderData) {
                http_response_code(404);
                echo json_encode(['error' => 'Commande non trouvée']);
                exit;
            }

            // Enrichir avec les infos du client depuis la table customers
            if (isset($orderData['customer_id']) && !isset($orderData['customer'])) {
                $customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$orderData['customer_id']]);
                if ($customer) {
                    $orderData['customer'] = $customer;
                }
            }

            $items = $order->getItems($_GET['id']);
            $orderData['items'] = $items;

            // Récupérer les échantillons
            $samples = $order->getOrderSamples($_GET['id']);
            $orderData['samples'] = $samples;

            // Formater pour le frontend
            $orderData = $order->formatForFrontend($orderData);

            http_response_code(200);
            echo json_encode(['order' => $orderData]);
            
        } else {
            // Liste de toutes les commandes
            $status = $_GET['status'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            $orders = $order->getAll($status, $limit, $offset);

            // Enrichir et formater chaque commande
            $formattedOrders = [];
            foreach ($orders as $ord) {
                // Enrichir avec les infos du client si manquantes
                if (isset($ord['customer_id']) && !isset($ord['customer'])) {
                    $customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$ord['customer_id']]);
                    if ($customer) {
                        $ord['customer'] = $customer;
                    }
                }
                // Ajouter le count d'échantillons
                $samples = $order->getOrderSamples($ord['id']);
                $ord['samples_count'] = count($samples);

                // Formater pour le frontend
                $formattedOrders[] = $order->formatForFrontend($ord);
            }

            $total = $order->countByStatus($status);

            // Statistiques
            $stats = [
                'pending' => $order->countByStatus('pending'),
                'confirmed' => $order->countByStatus('confirmed'),
                'in_production' => $order->countByStatus('in_production'),
                'shipped' => $order->countByStatus('shipped'),
                'delivered' => $order->countByStatus('delivered'),
                'cancelled' => $order->countByStatus('cancelled')
            ];

            http_response_code(200);
            echo json_encode([
                'orders' => $formattedOrders,
                'total' => $total,
                'stats' => $stats
            ]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Mettre à jour une commande
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['order_id']) || !isset($data['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'order_id et status requis']);
            exit;
        }

        // Récupérer les détails de la commande avant mise à jour
        $orderData = $order->getById($data['order_id']);

        if (!$orderData) {
            http_response_code(404);
            echo json_encode(['error' => 'Commande non trouvée']);
            exit;
        }

        // Mettre à jour le statut
        $order->updateStatus($data['order_id'], $data['status'], $data['admin_notes'] ?? null);

        // Créer une notification pour le client
        if (isset($orderData['customer_id'])) {
            $notification = new Notification();
            $notification->createOrderStatusNotification(
                $orderData['customer_id'],
                $data['order_id'],
                $orderData['order_number'],
                $data['status']
            );
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Statut mis à jour'
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
