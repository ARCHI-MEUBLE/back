<?php
/**
 * ArchiMeuble - API Users Management (Admin)
 * Gère la liste et modification des utilisateurs/admins
 * Auteur : Collins
 * Date : 2025-10-23
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Admin.php';

$session = Session::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// Vérifier que l'utilisateur est admin
if (!$session->get('is_admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

$user = new User();
$admin = new Admin();

try {
    switch ($method) {
        case 'GET':
            // Liste tous les utilisateurs et admins
            $users = $user->getAll();
            $admins = $admin->getAll();

            // Formater les utilisateurs
            $formattedUsers = array_map(function($u) {
                return [
                    'id' => $u['id'],
                    'email' => $u['email'],
                    'name' => $u['name'] ?? null,
                    'type' => 'user',
                    'created_at' => $u['created_at']
                ];
            }, $users);

            // Formater les admins
            $formattedAdmins = array_map(function($a) {
                return [
                    'id' => $a['id'],
                    'email' => $a['email'],
                    'username' => $a['username'] ?? null,
                    'type' => 'admin',
                    'created_at' => $a['created_at']
                ];
            }, $admins);

            echo json_encode([
                'users' => $formattedUsers,
                'admins' => $formattedAdmins
            ]);
            break;

        case 'PUT':
            // Modifier un mot de passe
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['id']) || !isset($input['type']) || !isset($input['newPassword'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID, type et nouveau mot de passe requis']);
                exit;
            }

            $id = $input['id'];
            $type = $input['type'];
            $newPassword = $input['newPassword'];

            // Validation du mot de passe
            if (strlen($newPassword) < 6) {
                http_response_code(400);
                echo json_encode(['error' => 'Le mot de passe doit contenir au moins 6 caractères']);
                exit;
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            if ($type === 'user') {
                $success = $user->update($id, ['password_hash' => $hashedPassword]);
            } elseif ($type === 'admin') {
                $success = $admin->update($id, ['password' => $hashedPassword]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Type invalide']);
                exit;
            }

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Échec de la modification']);
            }
            break;

        case 'DELETE':
            // Supprimer un utilisateur (pas les admins pour sécurité)
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['id']) || !isset($input['type'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID et type requis']);
                exit;
            }

            if ($input['type'] !== 'user') {
                http_response_code(403);
                echo json_encode(['error' => 'Impossible de supprimer un admin']);
                exit;
            }

            $success = $user->delete($input['id']);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Échec de la suppression']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur', 'message' => $e->getMessage()]);
}
