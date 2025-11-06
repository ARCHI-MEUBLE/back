<?php
/**
 * API: Génération et téléchargement de facture
 * GET /api/orders/invoice.php?id={orderId}
 *
 * Génère une facture PDF pour une commande payée
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

// Vérifier l'authentification (client ou admin)
$isClient = isset($_SESSION['customer_id']);
$isAdmin = isset($_SESSION['admin_id']);

if (!$isClient && !$isAdmin) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de commande requis']);
    exit;
}

require_once __DIR__ . '/../../models/Order.php';
require_once __DIR__ . '/../../models/Customer.php';
require_once __DIR__ . '/../../services/InvoiceService.php';
require_once __DIR__ . '/../../core/Database.php';

try {
    $orderId = (int)$_GET['id'];
    $orderModel = new Order();
    $customerModel = new Customer();
    $invoiceService = new InvoiceService();

    // Récupérer la commande
    $order = $orderModel->getById($orderId);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Commande introuvable']);
        exit;
    }

    // Vérifier que la commande appartient au client (si client connecté)
    if ($isClient && $order['customer_id'] != $_SESSION['customer_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }

    // Vérifier que la commande est payée
    if ($order['payment_status'] !== 'paid') {
        http_response_code(400);
        echo json_encode(['error' => 'La commande doit être payée pour générer une facture']);
        exit;
    }

    // Récupérer le client et les items
    $customer = $customerModel->getById($order['customer_id']);
    $items = $orderModel->getOrderItems($orderId);

    // Générer la facture
    $invoice = $invoiceService->generateInvoice($order, $customer, $items);

    // Si on veut retourner JSON avec l'URL
    if (isset($_GET['json']) && $_GET['json'] === 'true') {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'invoice_number' => $invoice['invoice_number'],
            'filename' => $invoice['filename'],
            'download_url' => "/backend/api/orders/invoice.php?id={$orderId}&download=true"
        ]);
        exit;
    }

    // Sinon, télécharger directement le fichier
    if (isset($_GET['download']) && $_GET['download'] === 'true') {
        $filepath = $invoice['filepath'];

        if (file_exists($filepath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $invoice['filename'] . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            // Fallback: télécharger la version HTML si PDF non disponible
            $htmlFilepath = str_replace('.pdf', '.html', $filepath);
            if (file_exists($htmlFilepath)) {
                header('Content-Type: text/html; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . str_replace('.pdf', '.html', $invoice['filename']) . '"');
                readfile($htmlFilepath);
                exit;
            }

            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la génération de la facture']);
            exit;
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
