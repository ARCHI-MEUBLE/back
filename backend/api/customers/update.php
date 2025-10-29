<?php
/**
 * API: Mettre à jour les informations du client
 * PUT /api/customers/update
 */

require_once __DIR__ . '/../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Vérifier l'authentification
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $customerId = $_SESSION['customer_id'];
    $db = Database::getInstance();

    // Récupérer le client actuel
    $customer = $db->queryOne(
        "SELECT * FROM customers WHERE id = ?",
        [$customerId]
    );

    if (!$customer) {
        http_response_code(404);
        echo json_encode(['error' => 'Client non trouvé']);
        exit;
    }

    // Préparer les champs à mettre à jour
    $updates = [];
    $params = [];

    if (isset($data['first_name']) && !empty($data['first_name'])) {
        $updates[] = "first_name = ?";
        $params[] = trim($data['first_name']);
    }

    if (isset($data['last_name']) && !empty($data['last_name'])) {
        $updates[] = "last_name = ?";
        $params[] = trim($data['last_name']);
    }

    if (isset($data['email']) && !empty($data['email'])) {
        $email = trim($data['email']);

        // Vérifier que l'email est valide
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Email invalide']);
            exit;
        }

        // Vérifier que l'email n'est pas déjà utilisé par un autre client
        $existingCustomer = $db->queryOne(
            "SELECT id FROM customers WHERE email = ? AND id != ?",
            [$email, $customerId]
        );

        if ($existingCustomer) {
            http_response_code(400);
            echo json_encode(['error' => 'Cet email est déjà utilisé']);
            exit;
        }

        $updates[] = "email = ?";
        $params[] = $email;

        // Mettre à jour l'email dans la session
        $_SESSION['customer_email'] = $email;
    }

    if (isset($data['phone'])) {
        $updates[] = "phone = ?";
        $params[] = trim($data['phone']) ?: null;
    }

    if (isset($data['address'])) {
        $updates[] = "address = ?";
        $params[] = trim($data['address']) ?: null;
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'Aucune donnée à mettre à jour']);
        exit;
    }

    // Construire et exécuter la requête
    $sql = "UPDATE customers SET " . implode(", ", $updates) . " WHERE id = ?";
    $params[] = $customerId;

    $result = $db->execute($sql, $params);

    if ($result) {
        // Récupérer les données mises à jour
        $updatedCustomer = $db->queryOne(
            "SELECT id, email, first_name, last_name, phone, address, created_at FROM customers WHERE id = ?",
            [$customerId]
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Informations mises à jour avec succès',
            'customer' => $updatedCustomer
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la mise à jour']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
