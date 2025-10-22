<?php
/**
 * ArchiMeuble - API Configurations
 * Endpoint pour gérer les configurations de meubles
 * Auteur : Collins
 * Date : 2025-10-20
 */

// Activer CORS
require_once __DIR__ . '/../core/Cors.php';
Cors::enable();

require_once __DIR__ . '/../models/Configuration.php';

try {
    $configModel = new Configuration();

    // Méthode GET : Récupérer les configurations
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Si un ID est fourni
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

            $config = $configModel->getById($id);

            if ($config) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $config
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Configuration non trouvée'
                ]);
            }

        // Si une session est fournie
        } elseif (isset($_GET['session'])) {
            $session = htmlspecialchars($_GET['session'], ENT_QUOTES, 'UTF-8');

            $configs = $configModel->getBySession($session);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'count' => count($configs),
                'data' => $configs
            ]);

        } else {
            // Récupérer toutes les configurations
            $configs = $configModel->getAll();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'count' => count($configs),
                'data' => $configs
            ]);
        }

    // Méthode POST : Créer une nouvelle configuration
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Lire les données JSON envoyées
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Valider les données requises
        if (!isset($data['user_session']) || !isset($data['prompt']) || !isset($data['price'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Données manquantes. Requis : user_session, prompt, price'
            ]);
            exit();
        }

        // Valider le prix
        if (!is_numeric($data['price']) || $data['price'] < 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Prix invalide'
            ]);
            exit();
        }

        // Valider le prompt (sécurité basique)
        if (strlen($data['prompt']) > 500) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Prompt trop long (max 500 caractères)'
            ]);
            exit();
        }

        // Créer la configuration
        $glbUrl = isset($data['glb_url']) ? $data['glb_url'] : null;
        $configId = $configModel->create(
            $data['user_session'],
            $data['prompt'],
            (float)$data['price'],
            $glbUrl
        );

        if ($configId !== false) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Configuration créée avec succès',
                'id' => $configId
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erreur lors de la création de la configuration'
            ]);
        }

    } else {
        // Méthode non autorisée
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Méthode non autorisée. Utilisez GET ou POST.'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur : ' . $e->getMessage()
    ]);
}
