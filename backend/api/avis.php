<?php
/**
 * ArchiMeuble - Endpoint pour les avis clients
 * GET /api/avis - Récupérer tous les avis
 * POST /api/avis - Créer un nouvel avis
 * DELETE /api/avis/:id - Supprimer un avis (admin uniquement)
 *
 * Date : 2025-10-28
 */

// Gestionnaire d'erreurs global pour retourner du JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur serveur',
        'message' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Erreur fatale',
            'message' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Cors.php';

// Activer CORS
Cors::enable();

$session = Session::getInstance();
$db = Database::getInstance()->getPDO();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * GET /api/avis - Récupérer tous les avis (triés par date décroissante)
 */
if ($method === 'GET') {
    try {
        $stmt = $db->prepare('
            SELECT id, user_id, author_name, rating, text, date, created_at
            FROM avis
            ORDER BY created_at DESC
        ');
        $stmt->execute();
        $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formater la réponse pour correspondre au format front
        $response = array_map(function($a) {
            return [
                'id' => (string)$a['id'],
                'authorName' => $a['author_name'],
                'rating' => (int)$a['rating'],
                'text' => $a['text'],
                'date' => $a['date']
            ];
        }, $avis);

        http_response_code(200);
        echo json_encode($response);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la récupération des avis', 'details' => $e->getMessage()]);
    }
    exit;
}

/**
 * POST /api/avis - Créer un nouvel avis
 */
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (!isset($input['rating']) || !isset($input['text'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Rating et texte sont requis']);
        exit;
    }

    $rating = (int)$input['rating'];
    $text = trim($input['text']);
    $authorName = isset($input['authorName']) ? trim($input['authorName']) : 'Utilisateur';
    $date = isset($input['date']) ? $input['date'] : date('Y-m-d');
    
    // Récupérer l'user_id depuis la session si disponible
    $userId = $session->get('user_id');

    // Validation du rating
    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Le rating doit être entre 1 et 5']);
        exit;
    }

    // Validation du texte
    if (empty($text)) {
        http_response_code(400);
        echo json_encode(['error' => 'Le texte de l\'avis ne peut pas être vide']);
        exit;
    }

    try {
        $stmt = $db->prepare('
            INSERT INTO avis (user_id, author_name, rating, text, date)
            VALUES (:user_id, :author_name, :rating, :text, :date)
        ');
        
        $stmt->execute([
            ':user_id' => $userId,
            ':author_name' => $authorName,
            ':rating' => $rating,
            ':text' => $text,
            ':date' => $date
        ]);

        $newId = $db->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'id' => (string)$newId,
            'authorName' => $authorName,
            'rating' => $rating,
            'text' => $text,
            'date' => $date
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la création de l\'avis', 'details' => $e->getMessage()]);
    }
    exit;
}

/**
 * DELETE /api/avis/:id - Supprimer un avis (admin uniquement)
 */
if ($method === 'DELETE') {
    // Vérifier que l'utilisateur est admin
    $isAdmin = $session->get('admin_id') !== null;
    
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès interdit. Vous devez être administrateur.']);
        exit;
    }

    // Extraire l'ID depuis l'URI (ex: /api/avis/5)
    $requestUri = $_SERVER['REQUEST_URI'];
    $pathParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
    
    // Le format attendu est: api/avis/{id}
    if (count($pathParts) < 3 || !is_numeric($pathParts[2])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID d\'avis invalide']);
        exit;
    }

    $avisId = (int)$pathParts[2];

    try {
        // Vérifier que l'avis existe
        $stmt = $db->prepare('SELECT id FROM avis WHERE id = :id');
        $stmt->execute([':id' => $avisId]);
        $exists = $stmt->fetch();

        if (!$exists) {
            http_response_code(404);
            echo json_encode(['error' => 'Avis non trouvé']);
            exit;
        }

        // Supprimer l'avis
        $stmt = $db->prepare('DELETE FROM avis WHERE id = :id');
        $stmt->execute([':id' => $avisId]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Avis supprimé avec succès']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la suppression de l\'avis', 'details' => $e->getMessage()]);
    }
    exit;
}

// Méthode non autorisée
http_response_code(405);
header('Allow: GET, POST, DELETE');
echo json_encode(['error' => 'Méthode non autorisée']);
