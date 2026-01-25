<?php
/**
 * API pour la gestion des façades
 * Endpoint: /backend/api/facades.php
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
    
    // GET - Récupérer toutes les façades ou une façade spécifique
    if ($method === 'GET') {
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            // Récupérer une façade spécifique
            $id = $matches[1];
            $stmt = $db->prepare('SELECT * FROM facades WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $facade = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($facade) {
                echo json_encode(['success' => true, 'data' => $facade]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Façade non trouvée']);
            }
        } else {
            // Récupérer toutes les façades actives
            $activeOnly = isset($_GET['active']) ? (bool)$_GET['active'] : false;
            $query = 'SELECT * FROM facades';
            if ($activeOnly) {
                $query .= ' WHERE is_active = 1';
            }
            $query .= ' ORDER BY created_at DESC';
            
            $result = $db->query($query);
            $facades = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $facades[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $facades]);
        }
    }
    
    // POST - Créer une nouvelle façade
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['name']) || !isset($input['width']) || !isset($input['height']) || !isset($input['depth'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Données manquantes']);
            exit;
        }
        
        $stmt = $db->prepare('
            INSERT INTO facades (name, description, width, height, depth, base_price, image_url, is_active)
            VALUES (:name, :description, :width, :height, :depth, :base_price, :image_url, :is_active)
        ');
        
        $stmt->bindValue(':name', $input['name'], SQLITE3_TEXT);
        $stmt->bindValue(':description', $input['description'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':width', $input['width'], SQLITE3_FLOAT);
        $stmt->bindValue(':height', $input['height'], SQLITE3_FLOAT);
        $stmt->bindValue(':depth', $input['depth'], SQLITE3_FLOAT);
        $stmt->bindValue(':base_price', $input['base_price'] ?? 0, SQLITE3_FLOAT);
        $stmt->bindValue(':image_url', $input['image_url'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':is_active', $input['is_active'] ?? 1, SQLITE3_INTEGER);
        
        $stmt->execute();
        $id = $db->lastInsertRowID();
        
        echo json_encode(['success' => true, 'data' => ['id' => $id]]);
    }
    
    // PUT - Mettre à jour une façade
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
            if (isset($input['description'])) {
                $fields[] = 'description = :description';
                $values[':description'] = [$input['description'], SQLITE3_TEXT];
            }
            if (isset($input['width'])) {
                $fields[] = 'width = :width';
                $values[':width'] = [$input['width'], SQLITE3_FLOAT];
            }
            if (isset($input['height'])) {
                $fields[] = 'height = :height';
                $values[':height'] = [$input['height'], SQLITE3_FLOAT];
            }
            if (isset($input['depth'])) {
                $fields[] = 'depth = :depth';
                $values[':depth'] = [$input['depth'], SQLITE3_FLOAT];
            }
            if (isset($input['base_price'])) {
                $fields[] = 'base_price = :base_price';
                $values[':base_price'] = [$input['base_price'], SQLITE3_FLOAT];
            }
            if (isset($input['image_url'])) {
                $fields[] = 'image_url = :image_url';
                $values[':image_url'] = [$input['image_url'], SQLITE3_TEXT];
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
            
            $fields[] = 'updated_at = CURRENT_TIMESTAMP';
            $query = 'UPDATE facades SET ' . implode(', ', $fields) . ' WHERE id = :id';
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            foreach ($values as $key => $value) {
                $stmt->bindValue($key, $value[0], $value[1]);
            }
            
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Façade mise à jour']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID manquant']);
        }
    }
    
    // DELETE - Supprimer une façade
    elseif ($method === 'DELETE') {
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = $matches[1];
            
            $stmt = $db->prepare('DELETE FROM facades WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Façade supprimée']);
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
