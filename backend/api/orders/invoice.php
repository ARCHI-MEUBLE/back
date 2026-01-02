<?php
/**
 * API: Génération et téléchargement de facture
 * GET /api/orders/invoice.php?id={orderId}
 *
 * Génère une facture PDF pour une commande payée
 */

// Activer l'affichage des erreurs pour le débogage (UNIQUEMENT SI PAS EN TÉLÉCHARGEMENT)
if (!isset($_GET['download']) || $_GET['download'] !== 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// Log des erreurs dans un fichier local
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

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
    // Activer l'affichage des erreurs pour le débogage
    // error_reporting(E_ALL);
    // ini_set('display_errors', 1);

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

    // Récupérer le client, les items et les échantillons
    $customer = $customerModel->getById($order['customer_id']);
    $items = $orderModel->getOrderItems($orderId);
    $samples = $orderModel->getOrderSamples($orderId);

    // Générer la facture
    $invoice = $invoiceService->generateInvoice($order, $customer, $items, $samples);

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

    // 1. Tenter de servir le PDF s'il est valide
    if (isset($_GET['download']) && $_GET['download'] === 'true') {
        $pdfPath = $invoice['filepath'];
        $htmlPath = str_replace('.pdf', '.html', $pdfPath);

        // Force cleanup of output buffer to avoid any noise in PDF/HTML
        if (ob_get_level()) ob_end_clean();

        if (file_exists($pdfPath) && filesize($pdfPath) > 500) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $invoice['filename'] . '"');
            header('Content-Length: ' . filesize($pdfPath));
            readfile($pdfPath);
            exit;
        }

        // 2. Fallback: servir le HTML si le PDF n'est pas disponible ou invalide
        if (file_exists($htmlPath)) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: inline; filename="' . str_replace('.pdf', '.html', $invoice['filename']) . '"');
            header('Content-Length: ' . filesize($htmlPath));
            readfile($htmlPath);
            exit;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Fichiers de facture introuvables']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
