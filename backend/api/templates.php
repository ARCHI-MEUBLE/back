<?php
/**
 * ArchiMeuble - API Templates
 * Endpoint pour récupérer les templates de meubles
 * Auteur : Collins
 * Date : 2025-10-20
 */

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../models/Template.php';

try {
    $templateModel = new Template();

    // Méthode GET : Récupérer tous les templates ou un template spécifique
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Si un ID est fourni dans l'URL
        if (isset($_GET['id'])) {
            $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

            if ($id === false || $id <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'ID invalide'
                ]);
                exit();
            }

            $template = $templateModel->getById($id);

            if ($template) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $template
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Template non trouvé'
                ]);
            }

        } else {
            // Récupérer tous les templates
            $templates = $templateModel->getAll();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'count' => count($templates),
                'data' => $templates
            ]);
        }

    } else {
        // Méthode non autorisée
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Méthode non autorisée. Utilisez GET.'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur : ' . $e->getMessage()
    ]);
}
