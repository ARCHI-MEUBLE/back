<?php
/**
 * API pour supprimer une commande non payée
 * POST /api/orders/delete.php
 */

// CORS headers
header('Access-Control-Allow-Origin: https://front-git-servertest-archimeuble.vercel.app');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

session_start();

require_once __DIR__ . '/../../models/Order.php';
require_once __DIR__ . '/../../core/auth.php';

// Vérifier l'authentification
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$customerId = $_SESSION['customer_id'];

// Récupérer les données POST
$input = json_decode(file_get_contents('php://input'), true);
$orderId = $input['order_id'] ?? null;

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de commande manquant']);
    exit;
}

try {
    $orderModel = new Order();

    // Récupérer la commande
    $order = $orderModel->getById($orderId);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Commande introuvable']);
        exit;
    }

    // Vérifier que la commande appartient au client
    if ($order['customer_id'] != $customerId) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }

    // Vérifier que la commande n'est pas payée
    if ($order['payment_status'] === 'paid') {
        http_response_code(400);
        echo json_encode(['error' => 'Impossible de supprimer une commande payée']);
        exit;
    }

    // Supprimer la commande
    $deleted = $orderModel->delete($orderId);

    if (!$deleted) {
        throw new Exception('Échec de la suppression');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Commande supprimée avec succès'
    ]);

} catch (Exception $e) {
    error_log("Erreur lors de la suppression de commande: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
