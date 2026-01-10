<?php
/**
 * ArchiMeuble - Endpoint des catégories
 * GET /api/categories - Récupérer toutes les catégories
 * GET /api/categories?id={id} - Récupérer une catégorie spécifique
 * POST /api/categories - Créer une nouvelle catégorie (admin uniquement)
 * PUT /api/categories - Modifier une catégorie (admin uniquement)
 * DELETE /api/categories - Supprimer une catégorie (admin uniquement)
 * PUT /api/categories/reorder - Réorganiser l'ordre des catégories (admin uniquement)
 *
 * Date : 2026-01-08
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Category.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$category = new Category();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Vérifie si l'utilisateur est admin
 */
function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../core/Session.php';
        Session::getInstance();
    }
    $isAdmin = isset($_SESSION['admin_email']) && !empty($_SESSION['admin_email']);
    error_log("Admin check - admin_email: " . ($_SESSION['admin_email'] ?? 'not set'));
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

    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }

    if (strpos($imagePath, '/') !== 0) {
        $imagePath = '/' . $imagePath;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

    if ($isLocal) {
        // En local, on retourne un chemin relatif pour que Next.js le gère via son proxy
        return $imagePath;
    }

    $protocol = 'https';
    $baseUrl = $protocol . '://' . $host;
    return $baseUrl . $imagePath;
}

/**
 * Convertit les chemins d'images dans un tableau de catégories
 */
function convertCategoryImagePaths($categories) {
    if (!is_array($categories)) {
        return $categories;
    }

    foreach ($categories as &$cat) {
        if (isset($cat['image_url'])) {
            $cat['image_url'] = convertImagePath($cat['image_url']);
        }
    }

    return $categories;
}

/**
 * Génère un slug à partir d'un nom
 */
function generateSlug($name) {
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');

    // Remplacer les caractères accentués
    $accents = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n'
    ];
    $slug = strtr($slug, $accents);

    return $slug;
}

/**
 * GET /api/categories
 */
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $categoryData = $category->getById($_GET['id']);
        if ($categoryData) {
            if (isset($categoryData['image_url'])) {
                $categoryData['image_url'] = convertImagePath($categoryData['image_url']);
            }
            http_response_code(200);
            echo json_encode($categoryData);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Catégorie non trouvée']);
        }
    } else {
        $onlyActive = isset($_GET['active']) && $_GET['active'] === 'true';
        $categories = $category->getAll($onlyActive);
        $categories = convertCategoryImagePaths($categories);
        http_response_code(200);
        echo json_encode(['categories' => $categories]);
    }
    exit;
}

/**
 * POST /api/categories - Créer une nouvelle catégorie (admin uniquement)
 */
if ($method === 'POST') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nom requis']);
        exit;
    }

    // Générer un slug si non fourni
    $slug = $input['slug'] ?? generateSlug($input['name']);
    $imageUrl = $input['image_url'] ?? $input['imageUrl'] ?? null;
    $description = $input['description'] ?? null;
    $displayOrder = $input['display_order'] ?? $input['displayOrder'] ?? 0;
    $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : (isset($input['isActive']) ? (bool)$input['isActive'] : true);

    try {
        $categoryId = $category->create(
            $input['name'],
            $slug,
            $description,
            $imageUrl,
            $displayOrder,
            $isActive
        );

        if ($categoryId) {
            $createdCategory = $category->getById($categoryId);
            if (isset($createdCategory['image_url'])) {
                $createdCategory['image_url'] = convertImagePath($createdCategory['image_url']);
            }
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'category' => $createdCategory
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Erreur lors de la création de la catégorie',
                'details' => 'Le slug existe peut-être déjà'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Exception lors de la création de la catégorie',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

/**
 * PUT /api/categories - Modifier une catégorie (admin uniquement)
 */
if ($method === 'PUT') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Gérer le réordonnancement
    if (isset($input['action']) && $input['action'] === 'reorder') {
        if (!isset($input['categoryIds']) || !is_array($input['categoryIds'])) {
            http_response_code(400);
            echo json_encode(['error' => 'IDs des catégories requis pour le réordonnancement']);
            exit;
        }

        if ($category->reorder($input['categoryIds'])) {
            http_response_code(200);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors du réordonnancement des catégories']);
        }
        exit;
    }

    // Modification normale
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de la catégorie requis']);
        exit;
    }

    $updateData = [];
    $allowedFields = ['name', 'slug', 'description', 'image_url', 'display_order', 'is_active'];

    // Convertir camelCase en snake_case
    if (isset($input['imageUrl'])) {
        $input['image_url'] = $input['imageUrl'];
    }
    if (isset($input['displayOrder'])) {
        $input['display_order'] = $input['displayOrder'];
    }
    if (isset($input['isActive'])) {
        $input['is_active'] = $input['isActive'] ? 1 : 0;
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

    if ($category->update($input['id'], $updateData)) {
        $updatedCategory = $category->getById($input['id']);
        if (isset($updatedCategory['image_url'])) {
            $updatedCategory['image_url'] = convertImagePath($updatedCategory['image_url']);
        }
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'category' => $updatedCategory
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la mise à jour de la catégorie']);
    }
    exit;
}

/**
 * DELETE /api/categories - Supprimer une catégorie (admin uniquement)
 */
if ($method === 'DELETE') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        exit;
    }

    $id = null;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['id'])) {
            $id = $input['id'];
        }
    }

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de la catégorie requis']);
        exit;
    }

    if ($category->delete($id)) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erreur lors de la suppression de la catégorie',
            'details' => 'La catégorie est peut-être utilisée par des modèles'
        ]);
    }
    exit;
}

// Méthode non supportée
http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);
