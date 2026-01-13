<?php
/**
 * API pour la gestion des matériaux de façades
 * Endpoint: /backend/api/facade-materials.php
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

// Debug log
error_log("facade-materials.php: METHOD=$method, PATH_INFO=$path");

try {
    $db = getDbConnection();
    
    // GET - Récupérer tous les matériaux ou un matériau spécifique
    if ($method === 'GET') {
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = $matches[1];
            $stmt = $db->prepare('SELECT * FROM facade_materials WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $material = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($material) {
                echo json_encode(['success' => true, 'data' => $material]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Matériau non trouvé']);
            }
        } else {
            $activeOnly = isset($_GET['active']) ? (bool)$_GET['active'] : false;
            $query = 'SELECT * FROM facade_materials';
            if ($activeOnly) {
                $query .= ' WHERE is_active = 1';
            }
            $query .= ' ORDER BY name ASC';
            
            $result = $db->query($query);
            $materials = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $materials[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $materials]);
        }
    }
    
    // POST - Créer un nouveau matériau
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['name']) || !isset($input['color_hex'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Données manquantes']);
            exit;
        }
        
        $stmt = $db->prepare('
            INSERT INTO facade_materials (name, color_hex, texture_url, price_modifier, price_per_m2, is_active)
            VALUES (:name, :color_hex, :texture_url, :price_modifier, :price_per_m2, :is_active)
        ');
        
        $stmt->bindValue(':name', $input['name'], SQLITE3_TEXT);
        $stmt->bindValue(':color_hex', $input['color_hex'], SQLITE3_TEXT);
        $stmt->bindValue(':texture_url', $input['texture_url'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':price_modifier', $input['price_modifier'] ?? 0, SQLITE3_FLOAT);
        $stmt->bindValue(':price_per_m2', $input['price_per_m2'] ?? 150, SQLITE3_FLOAT);
        $stmt->bindValue(':is_active', $input['is_active'] ?? 1, SQLITE3_INTEGER);
        
        $stmt->execute();
        $id = $db->lastInsertRowID();
        
        echo json_encode(['success' => true, 'data' => ['id' => $id]]);
    }
    
    // PUT - Mettre à jour un matériau
    elseif ($method === 'PUT') {
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = $matches[1];
            $input = json_decode(file_get_contents('php://input'), true);
            
            $fields = [];
            $values = [];
            
            if (isset($input['name'])) {
                $fields[] = 'name = :name';
                $values[':name'] = [$input['name'], SQLITE3_TEXT];
            }
            if (isset($input['color_hex'])) {
                $fields[] = 'color_hex = :color_hex';
                $values[':color_hex'] = [$input['color_hex'], SQLITE3_TEXT];
            }
            if (isset($input['texture_url'])) {
                $fields[] = 'texture_url = :texture_url';
                $values[':texture_url'] = [$input['texture_url'], SQLITE3_TEXT];
            }
            if (isset($input['price_modifier'])) {
                $fields[] = 'price_modifier = :price_modifier';
                $values[':price_modifier'] = [$input['price_modifier'], SQLITE3_FLOAT];
            }
            if (isset($input['price_per_m2'])) {
                $fields[] = 'price_per_m2 = :price_per_m2';
                $values[':price_per_m2'] = [$input['price_per_m2'], SQLITE3_FLOAT];
            }
            if (isset($input['is_active'])) {
                $fields[] = 'is_active = :is_active';
                $values[':is_active'] = [$input['is_active'], SQLITE3_INTEGER];
            }
            
            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
                exit;
            }
            
            $query = 'UPDATE facade_materials SET ' . implode(', ', $fields) . ' WHERE id = :id';
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            foreach ($values as $key => $value) {
                $stmt->bindValue($key, $value[0], $value[1]);
            }
            
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Matériau mis à jour']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID manquant']);
        }
    }
    
    // DELETE - Supprimer un matériau
    elseif ($method === 'DELETE') {
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = $matches[1];
            
            $stmt = $db->prepare('DELETE FROM facade_materials WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Matériau supprimé']);
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
