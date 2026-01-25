<?php
/**
 * API Publique - Télécharger la facture via payment_intent_id
 * GET /api/payment-link/download-invoice?payment_intent_id=xxx
 *
 * Endpoint public sécurisé pour télécharger la facture après paiement
 */

// Désactiver l'affichage des erreurs
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/env.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

if (!isset($_GET['payment_intent_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'payment_intent_id requis']);
    exit;
}

try {
    $paymentIntentId = trim($_GET['payment_intent_id']);

    require_once __DIR__ . '/../../core/Database.php';
    require_once __DIR__ . '/../../models/Order.php';
    require_once __DIR__ . '/../../models/Customer.php';
    require_once __DIR__ . '/../../services/InvoiceService.php';

    $db = Database::getInstance();
    $orderModel = new Order();
    $customerModel = new Customer();
    $invoiceService = new InvoiceService();

    // Trouver la commande via le payment_intent_id
    $query = "SELECT id, customer_id, payment_status FROM orders WHERE stripe_payment_intent_id = ?";
    $order = $db->queryOne($query, [$paymentIntentId]);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Commande introuvable']);
        exit;
    }

    // Vérifier que la commande est payée
    if ($order['payment_status'] !== 'paid') {
        http_response_code(400);
        echo json_encode(['error' => 'La commande doit être payée pour télécharger la facture']);
        exit;
    }

    // Récupérer les détails complets
    $fullOrder = $orderModel->getById($order['id']);
    $customer = $customerModel->getById($order['customer_id']);
    $items = $orderModel->getOrderItems($order['id']);
    $samples = $orderModel->getOrderSamples($order['id']);

    // Générer ou récupérer la facture
    $invoice = $invoiceService->generateInvoice($fullOrder, $customer, $items, $samples);

    $pdfPath = $invoice['filepath'];

    // Nettoyer le buffer de sortie
    if (ob_get_level()) ob_end_clean();

    // Vérifier que le PDF existe et est valide
    if (file_exists($pdfPath) && filesize($pdfPath) > 500) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $invoice['filename'] . '"');
        header('Content-Length: ' . filesize($pdfPath));
        readfile($pdfPath);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Facture PDF introuvable']);
        exit;
    }

} catch (Exception $e) {
    error_log("Error in download-invoice: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la génération de la facture']);
}
