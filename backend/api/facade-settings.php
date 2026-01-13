<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

try {
    switch ($method) {
        case 'GET':
            // Récupérer tous les paramètres
            $query = "SELECT * FROM facade_settings ORDER BY setting_key";
            $result = $db->query($query);
            
            $settings = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $settings[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
            break;

        case 'PUT':
            // Mettre à jour un paramètre
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['setting_key']) || !isset($input['setting_value'])) {
                throw new Exception('setting_key et setting_value sont requis');
            }

            $stmt = $db->prepare('
                UPDATE facade_settings 
                SET setting_value = :value, updated_at = CURRENT_TIMESTAMP
                WHERE setting_key = :key
            ');
            $stmt->bindValue(':value', $input['setting_value'], SQLITE3_TEXT);
            $stmt->bindValue(':key', $input['setting_key'], SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Paramètre mis à jour avec succès'
                ]);
            } else {
                throw new Exception('Erreur lors de la mise à jour');
            }
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Méthode non autorisée'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
