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
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $material = $stmt->fetch(PDO::FETCH_ASSOC);

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
                $query .= ' WHERE is_active = TRUE';
            }
            $query .= ' ORDER BY name ASC';

            $result = $db->query($query);
            $materials = $result->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $materials]);
        }
    }

    // POST - Créer un nouveau matériau
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nom manquant']);
            exit;
        }

        $colorHex = $input['color_hex'] ?? '';
        $textureUrl = $input['texture_url'] ?? '';

        // Validation stricte: soit couleur SOIT texture, jamais les deux
        if (!$colorHex && !$textureUrl) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Fournir une couleur (hex) OU une image de texture']);
            exit;
        }

        // Si texture fournie: ignorer la couleur (elle devient #FFFFFF par défaut)
        // Si couleur fournie sans texture: vider la texture
        if ($textureUrl) {
            $colorHex = '#FFFFFF'; // Default white pour texture
        } else {
            $textureUrl = ''; // Vider la texture si couleur seule
        }

        $stmt = $db->prepare('
            INSERT INTO facade_materials (name, color_hex, texture_url, price_modifier, price_per_m2, is_active)
            VALUES (:name, :color_hex, :texture_url, :price_modifier, :price_per_m2, :is_active)
        ');

        $stmt->bindValue(':name', $input['name'], PDO::PARAM_STR);
        $stmt->bindValue(':color_hex', $colorHex, PDO::PARAM_STR);
        $stmt->bindValue(':texture_url', $textureUrl, PDO::PARAM_STR);
        $stmt->bindValue(':price_modifier', $input['price_modifier'] ?? 0);
        $stmt->bindValue(':price_per_m2', $input['price_per_m2'] ?? 150);
        $stmt->bindValue(':is_active', (isset($input['is_active']) && $input['is_active'] !== '') ? filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN) : true, PDO::PARAM_BOOL);

        $stmt->execute();
        $id = $db->lastInsertId();

        echo json_encode(['success' => true, 'data' => ['id' => $id]]);
    }

    // PUT - Mettre à jour un matériau
    elseif ($method === 'PUT') {
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = $matches[1];
            $input = json_decode(file_get_contents('php://input'), true);

            $colorHex = $input['color_hex'] ?? '';
            $textureUrl = $input['texture_url'] ?? '';

            // Validation: soit couleur SOIT texture, pas les deux
            if (!$colorHex && !$textureUrl) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Fournir une couleur (hex) OU une image de texture']);
                exit;
            }

            // Si texture fournie: ignorer la couleur (elle devient vide)
            // Si couleur fournie sans texture: garder juste la couleur
            if ($textureUrl) {
                $colorHex = '#FFFFFF'; // Default white pour texture
            } else {
                $textureUrl = ''; // Vider la texture si couleur seule
            }

            $fields = [];
            $values = [];

            if (isset($input['name'])) {
                $fields[] = 'name = :name';
                $values[':name'] = [$input['name'], PDO::PARAM_STR];
            }

            $fields[] = 'color_hex = :color_hex';
            $values[':color_hex'] = [$colorHex, PDO::PARAM_STR];

            $fields[] = 'texture_url = :texture_url';
            $values[':texture_url'] = [$textureUrl, PDO::PARAM_STR];

            if (isset($input['price_modifier'])) {
                $fields[] = 'price_modifier = :price_modifier';
                $values[':price_modifier'] = [$input['price_modifier'], PDO::PARAM_STR];
            }
            if (isset($input['price_per_m2'])) {
                $fields[] = 'price_per_m2 = :price_per_m2';
                $values[':price_per_m2'] = [$input['price_per_m2'], PDO::PARAM_STR];
            }
            if (isset($input['is_active'])) {
                $fields[] = 'is_active = :is_active';
                $values[':is_active'] = [($input['is_active'] !== '') ? filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN) : true, PDO::PARAM_BOOL];
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
                exit;
            }

            $query = 'UPDATE facade_materials SET ' . implode(', ', $fields) . ' WHERE id = :id';

            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
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
        $id = null;
        if (preg_match('/^\/(\d+)$/', $path, $matches)) {
            $id = $matches[1];
        } elseif (isset($_GET['id'])) {
            $id = $_GET['id'];
        }

        if ($id) {
            $stmt = $db->prepare('DELETE FROM facade_materials WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
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
