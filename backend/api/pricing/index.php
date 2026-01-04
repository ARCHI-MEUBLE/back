<?php
/**
 * Pricing API
 * Manages price per cubic meter for furniture
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../core/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Obtenir la connexion PDO à la base de données
$db = Database::getInstance()->getPDO();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Get all pricing options or a specific one
        if (isset($_GET['name'])) {
            $stmt = $db->prepare('SELECT * FROM pricing WHERE name = :name AND is_active = 1');
            $stmt->execute([':name' => $_GET['name']]);
            $pricing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pricing) {
                // Si le tarif demandé n'existe pas, retourner le premier tarif actif ou un tarif par défaut
                $stmt = $db->query('SELECT * FROM pricing WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
                $pricing = $stmt->fetch(PDO::FETCH_ASSOC);

                // Si toujours aucun tarif, créer un tarif par défaut
                if (!$pricing) {
                    $pricing = [
                        'id' => 0,
                        'name' => 'default',
                        'description' => 'Tarif par défaut',
                        'price_per_m3' => 2500.00,
                        'is_active' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'data' => $pricing
            ]);
        } else {
            // Get all pricing
            $stmt = $db->query('SELECT * FROM pricing WHERE is_active = 1 ORDER BY price_per_m3 ASC');
            $pricings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $pricings
            ]);
        }

    } elseif ($method === 'POST') {
        // Create new pricing (admin only - you should add authentication)
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['name']) || !isset($data['price_per_m3'])) {
            throw new Exception('Nom et prix par m³ requis');
        }

        $stmt = $db->prepare('
            INSERT INTO pricing (name, description, price_per_m3, is_active)
            VALUES (:name, :description, :price_per_m3, :is_active)
        ');

        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? '',
            ':price_per_m3' => $data['price_per_m3'],
            ':is_active' => $data['is_active'] ?? 1
        ]);

        $pricingId = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Tarif créé avec succès',
            'data' => ['id' => $pricingId]
        ]);

    } elseif ($method === 'PUT') {
        // Update existing pricing (admin only)
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id'])) {
            throw new Exception('ID requis');
        }

        $updates = [];
        $params = [':id' => $data['id']];

        if (isset($data['name'])) {
            $updates[] = 'name = :name';
            $params[':name'] = $data['name'];
        }

        if (isset($data['description'])) {
            $updates[] = 'description = :description';
            $params[':description'] = $data['description'];
        }

        if (isset($data['price_per_m3'])) {
            $updates[] = 'price_per_m3 = :price_per_m3';
            $params[':price_per_m3'] = $data['price_per_m3'];
        }

        if (isset($data['is_active'])) {
            $updates[] = 'is_active = :is_active';
            $params[':is_active'] = $data['is_active'];
        }

        $updates[] = 'updated_at = CURRENT_TIMESTAMP';

        if (empty($updates)) {
            throw new Exception('Aucune donnée à mettre à jour');
        }

        $sql = 'UPDATE pricing SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'Tarif mis à jour avec succès'
        ]);

    } elseif ($method === 'DELETE') {
        // Soft delete pricing (admin only)
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id'])) {
            throw new Exception('ID requis');
        }

        $stmt = $db->prepare('UPDATE pricing SET is_active = 0 WHERE id = :id');
        $stmt->execute([':id' => $data['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Tarif supprimé avec succès'
        ]);

    } else {
        throw new Exception('Méthode non autorisée');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
