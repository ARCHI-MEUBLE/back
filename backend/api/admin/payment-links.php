<?php
/**
 * API Admin - Gérer les liens de paiement
 * GET /api/admin/payment-links?order_id=X - Lister les liens d'une commande
 * POST /api/admin/payment-links/revoke - Révoquer un lien
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
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();

    // LISTER LES LIENS D'UNE COMMANDE
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['order_id'])) {
        $orderId = intval($_GET['order_id']);

        $query = "SELECT pl.*, o.order_number
                  FROM payment_links pl
                  LEFT JOIN orders o ON pl.order_id = o.id
                  WHERE pl.order_id = ?
                  ORDER BY pl.created_at DESC";

        $links = $db->query($query, [$orderId]);

        // Ajouter l'URL complète pour chaque lien
        foreach ($links as &$link) {
            $frontendUrl = getenv('FRONTEND_URL') ?: 'http://127.0.0.1:3000';
            $link['url'] = rtrim($frontendUrl, '/') . '/paiement/' . $link['token'];
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'links' => $links
        ]);
        exit;
    }

    // RÉVOQUER UN LIEN
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['action']) || $data['action'] !== 'revoke') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action invalide']);
            exit;
        }

        if (!isset($data['link_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID de lien manquant']);
            exit;
        }

        $linkId = intval($data['link_id']);

        // Vérifier que le lien existe
        $checkQuery = "SELECT * FROM payment_links WHERE id = ?";
        $link = $db->queryOne($checkQuery, [$linkId]);

        if (!$link) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Lien introuvable']);
            exit;
        }

        // Révoquer le lien
        $updateQuery = "UPDATE payment_links SET status = 'revoked' WHERE id = ?";
        $db->execute($updateQuery, [$linkId]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Lien révoqué avec succès'
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
