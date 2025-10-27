<?php
/**
 * ArchiMeuble - Endpoint des modèles de meubles
 * GET /api/models - Récupérer tous les modèles
 * GET /api/models?id={id} - Récupérer un modèle spécifique
 * POST /api/models - Créer un nouveau modèle (admin uniquement)
 * PUT /api/models/{id} - Modifier un modèle (admin uniquement)
 * DELETE /api/models/{id} - Supprimer un modèle (admin uniquement)
 *
 * Date : 2025-10-21
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Cors.php';
require_once __DIR__ . '/../models/Model.php';

// Activer CORS
Cors::enable();

$session = Session::getInstance();
$model = new Model();

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Vérifie si l'utilisateur est admin
 */
function isAdmin() {
    global $session;
    $isAdmin = $session->has('is_admin') && $session->get('is_admin') === true;

    // Log pour debug
    error_log("Session ID: " . $session->getId());
    error_log("is_admin: " . ($session->has('is_admin') ? 'true' : 'false'));
    error_log("is_admin value: " . ($session->get('is_admin') ? 'true' : 'false'));
    error_log("Result: " . ($isAdmin ? 'true' : 'false'));

    return $isAdmin;
}

/**
 * Convertit un chemin relatif d'image en URL complète
 */
function convertImagePath($imagePath) {
    if (!$imagePath) {
        return null;
    }

    // Si c'est déjà une URL complète, la retourner telle quelle
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }

    // S'assurer que le chemin commence par /
    if (strpos($imagePath, '/') !== 0) {
        $imagePath = '/' . $imagePath;
    }

    // Détecter l'environnement
    $host = $_SERVER['HTTP_HOST'];
    $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

    if ($isLocal) {
        // EN LOCAL: Les images sont dans le frontend Next.js (localhost:3000)
        // car le frontend les sauvegarde dans front/public/uploads/
        $frontendUrl = getenv('FRONTEND_URL') ?: 'http://localhost:3000';
        error_log("Converting image path (LOCAL): $imagePath -> $frontendUrl$imagePath");
        return $frontendUrl . $imagePath;
    }

    // EN PRODUCTION: Les images sont sur Railway backend
    // car le frontend proxie l'upload vers le backend
    $protocol = 'https';
    $baseUrl = $protocol . '://' . $host;
    error_log("Converting image path (PRODUCTION): $imagePath -> $baseUrl$imagePath");

    return $baseUrl . $imagePath;
}

/**
 * Convertit les chemins d'images dans un tableau de modèles
 */
function convertModelImagePaths($models) {
    if (!is_array($models)) {
        return $models;
    }

    foreach ($models as &$model) {
        if (isset($model['image_url'])) {
            $model['image_url'] = convertImagePath($model['image_url']);
        }
    }

    return $models;
}

/**
 * GET /api/models
 */
if ($method === 'GET') {
    // Récupérer un modèle spécifique ou tous les modèles
    if (isset($_GET['id'])) {
        $modelData = $model->getById($_GET['id']);
        if ($modelData) {
            // Convertir le chemin de l'image en URL complète
            if (isset($modelData['image_url'])) {
                $modelData['image_url'] = convertImagePath($modelData['image_url']);
            }
            http_response_code(200);
            echo json_encode($modelData);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Modèle non trouvé']);
        }
    } else {
        $models = $model->getAll();
        // Convertir tous les chemins d'images en URLs complètes
        $models = convertModelImagePaths($models);
        http_response_code(200);
        echo json_encode(['models' => $models]);
    }
    exit;
}

/**
 * POST /api/models - Créer un nouveau modèle (admin uniquement)
 */
if ($method === 'POST') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['name']) || !isset($input['prompt'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nom et prompt requis']);
        exit;
    }

    // Validation : le prompt doit contenir la lettre "b" (base/planche du bas)
    if (strpos($input['prompt'], 'b') === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Le prompt doit contenir "b" (planche de base obligatoire)']);
        exit;
    }

    // Support des deux formats : camelCase et snake_case
    $imageUrl = $input['imageUrl'] ?? $input['image_url'] ?? $input['imagePath'] ?? $input['image_path'] ?? null;
    $price = $input['price'] ?? $input['basePrice'] ?? $input['base_price'] ?? null;

    try {
        $modelId = $model->create(
            $input['name'],
            $input['description'] ?? null,
            $input['prompt'],
            $price,
            $imageUrl
        );

        if ($modelId) {
            $createdModel = $model->getById($modelId);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'model' => $createdModel
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Erreur lors de la création du modèle',
                'debug' => [
                    'name' => $input['name'],
                    'description' => $input['description'] ?? null,
                    'prompt' => $input['prompt'],
                    'price' => $price,
                    'imageUrl' => $imageUrl
                ]
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Exception lors de la création du modèle',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    exit;
}

/**
 * PUT /api/models - Modifier un modèle (admin uniquement)
 */
if ($method === 'PUT') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID du modèle requis']);
        exit;
    }

    // Validation : si le prompt est modifié, il doit contenir "b"
    if (isset($input['prompt']) && strpos($input['prompt'], 'b') === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Le prompt doit contenir "b" (planche de base obligatoire)']);
        exit;
    }

    $updateData = [];
    $allowedFields = ['name', 'description', 'prompt', 'price', 'image_url'];

    // Convertir camelCase en snake_case si nécessaire
    if (isset($input['imageUrl'])) {
        $input['image_url'] = $input['imageUrl'];
    }
    if (isset($input['imagePath'])) {
        $input['image_url'] = $input['imagePath'];
    }
    if (isset($input['basePrice'])) {
        $input['price'] = $input['basePrice'];
    }

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateData[$field] = $input[$field];
        }
    }

    if (empty($updateData)) {
        http_response_code(400);
        echo json_encode(['error' => 'Aucune donnée à mettre à jour']);
        exit;
    }

    if ($model->update($input['id'], $updateData)) {
        $updatedModel = $model->getById($input['id']);
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'model' => $updatedModel
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la mise à jour du modèle']);
    }
    exit;
}

/**
 * DELETE /api/models - Supprimer un modèle (admin uniquement)
 */
if ($method === 'DELETE') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID du modèle requis']);
        exit;
    }

    if ($model->delete($input['id'])) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la suppression du modèle']);
    }
    exit;
}

// Méthode non supportée
http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);
