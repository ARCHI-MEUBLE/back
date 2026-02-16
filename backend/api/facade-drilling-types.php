<?php
/**
 * API pour la gestion des types de perçages
 * Endpoint: /backend/api/facade-drilling-types.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';

try {
    $db = getDbConnection();

    // GET - Récupérer tous les types de perçages
    if ($method === 'GET') {
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = $matches[1];
            $stmt = $db->prepare('SELECT * FROM facade_drilling_types WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $type = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($type) {
                echo json_encode(['success' => true, 'data' => $type]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Type de perçage non trouvé']);
            }
        } else {
            $activeOnly = isset($_GET['active']) ? (bool)$_GET['active'] : false;
            $query = 'SELECT * FROM facade_drilling_types';
            if ($activeOnly) {
                $query .= ' WHERE is_active = TRUE';
            }
            $query .= ' ORDER BY name ASC';

            $result = $db->query($query);
            $types = $result->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $types]);
        }
    }

    // POST - Créer un nouveau type
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nom manquant']);
            exit;
        }

        $stmt = $db->prepare('
            INSERT INTO facade_drilling_types (name, description, icon_svg, price, is_active)
            VALUES (:name, :description, :icon_svg, :price, :is_active)
        ');

        $stmt->bindValue(':name', $input['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $input['description'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':icon_svg', $input['icon_svg'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':price', $input['price'] ?? 0);
        $stmt->bindValue(':is_active', $input['is_active'] ?? 1, PDO::PARAM_INT);

        $stmt->execute();
        $id = $db->lastInsertId();

        echo json_encode(['success' => true, 'data' => ['id' => $id]]);
    }

    // PUT - Mettre à jour un type
    elseif ($method === 'PUT') {
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = $matches[1];
            $input = json_decode(file_get_contents('php://input'), true);

            $fields = [];
            $values = [];

            if (isset($input['name'])) {
                $fields[] = 'name = :name';
                $values[':name'] = [$input['name'], PDO::PARAM_STR];
            }
            if (isset($input['description'])) {
                $fields[] = 'description = :description';
                $values[':description'] = [$input['description'], PDO::PARAM_STR];
            }
            if (isset($input['icon_svg'])) {
                $fields[] = 'icon_svg = :icon_svg';
                $values[':icon_svg'] = [$input['icon_svg'], PDO::PARAM_STR];
            }
            if (isset($input['price'])) {
                $fields[] = 'price = :price';
                $values[':price'] = [$input['price'], PDO::PARAM_STR];
            }
            if (isset($input['is_active'])) {
                $fields[] = 'is_active = :is_active';
                $values[':is_active'] = [$input['is_active'], PDO::PARAM_INT];
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
                exit;
            }

            $query = 'UPDATE facade_drilling_types SET ' . implode(', ', $fields) . ' WHERE id = :id';

            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            foreach ($values as $key => $value) {
                $stmt->bindValue($key, $value[0], $value[1]);
            }

            $stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Type de perçage mis à jour']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID manquant']);
        }
    }

    // DELETE
    elseif ($method === 'DELETE') {
        $id = null;
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = $matches[1];
        } elseif (isset($_GET['id'])) {
            $id = $_GET['id'];
        }

        if ($id) {
            $stmt = $db->prepare('DELETE FROM facade_drilling_types WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Type de perçage supprimé']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID manquant']);
        }
    }

    else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
