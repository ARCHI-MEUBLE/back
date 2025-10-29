<?php
/**
 * ArchiMeuble - Endpoint de gestion des utilisateurs et admins
 * GET /api/admin/users - Récupérer tous les comptes
 * PUT /api/admin/users - Modifier le mot de passe d'un compte
 * DELETE /api/admin/users - Supprimer un compte
 *
 * Date : 2025-10-23
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Cors.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Admin.php';

// Activer CORS
Cors::enable();

$session = Session::getInstance();

// Vérifier que l'utilisateur est admin
if (!$session->has('is_admin') || $session->get('is_admin') !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$userModel = new User();
$adminModel = new Admin();

/**
 * Vérifie l'existence d'une colonne dans une table SQLite
 */
function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            if (isset($col['name']) && $col['name'] === $column) {
                return true;
            }
        }
    } catch (Exception $e) {
        // ignore
    }
    return false;
}

/**
 * GET /api/admin/users - Récupérer tous les comptes
 */
if ($method === 'GET') {
    try {
        $db = Database::getInstance()->getPDO();

        // Récupérer tous les utilisateurs
        $usersStmt = $db->query('SELECT id, email, name, created_at FROM users ORDER BY created_at DESC');
        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer tous les admins
        $adminsStmt = $db->query('SELECT id, email, username, created_at FROM admins ORDER BY created_at DESC');
        $admins = $adminsStmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'users' => array_map(function($user) {
                return [
                    'id' => (string)$user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'type' => 'user',
                    'created_at' => $user['created_at']
                ];
            }, $users),
            'admins' => array_map(function($admin) {
                return [
                    'id' => (int)$admin['id'],
                    'email' => $admin['email'],
                    'username' => $admin['username'],
                    'type' => 'admin',
                    'created_at' => $admin['created_at']
                ];
            }, $admins)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la récupération des comptes: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * PUT /api/admin/users - Modifier le mot de passe d'un compte
 */
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Log pour debugging
    error_log("[ADMIN-USERS PUT] Input reçu: " . json_encode($input));

    if (!isset($input['id']) || !isset($input['type']) || !isset($input['newPassword'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres manquants (id, type, newPassword requis)']);
        exit;
    }

    $id = $input['id'];
    $type = $input['type'];
    $newPassword = $input['newPassword'];

    error_log("[ADMIN-USERS PUT] Modification mot de passe - ID: $id, Type: $type");

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Le mot de passe doit contenir au moins 6 caractères']);
        exit;
    }

    try {
        $db = Database::getInstance()->getPDO();
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        if ($type === 'user') {
            // Par défaut, les utilisateurs utilisent password_hash
            $targetColumn = columnExists($db, 'users', 'password_hash') ? 'password_hash' : (columnExists($db, 'users', 'password') ? 'password' : null);
            if ($targetColumn === null) {
                http_response_code(500);
                echo json_encode(['error' => "Colonne de mot de passe introuvable pour 'users'"]);
                exit;
            }
            error_log("[ADMIN-USERS PUT] User - Colonne: $targetColumn, ID: $id");
            $stmt = $db->prepare("UPDATE users SET $targetColumn = :password WHERE id = :id");
            $stmt->execute([
                ':password' => $hashedPassword,
                ':id' => $id
            ]);
        } elseif ($type === 'admin') {
            // Les admins peuvent avoir 'password' (legacy) ou 'password_hash'
            $targetColumn = columnExists($db, 'admins', 'password_hash') ? 'password_hash' : (columnExists($db, 'admins', 'password') ? 'password' : null);
            if ($targetColumn === null) {
                http_response_code(500);
                echo json_encode(['error' => "Colonne de mot de passe introuvable pour 'admins'"]);
                exit;
            }
            error_log("[ADMIN-USERS PUT] Admin - Colonne: $targetColumn, ID: $id (type: " . gettype($id) . ")");
            $stmt = $db->prepare("UPDATE admins SET $targetColumn = :password WHERE id = :id");
            $stmt->execute([
                ':password' => $hashedPassword,
                ':id' => $id
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Type de compte invalide']);
            exit;
        }

        $rowCount = $stmt->rowCount();
        error_log("[ADMIN-USERS PUT] Lignes affectées: $rowCount");

        if ($rowCount > 0) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Compte non trouvé']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la modification: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * DELETE /api/admin/users - Supprimer un compte
 */
if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id']) || !isset($input['type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres manquants (id, type requis)']);
        exit;
    }

    $id = $input['id'];
    $type = $input['type'];

    try {
        $db = Database::getInstance()->getPDO();

        if ($type === 'user') {
            $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id' => $id]);
        } elseif ($type === 'admin') {
            // Empêcher la suppression du dernier admin
            $countStmt = $db->query('SELECT COUNT(*) as count FROM admins');
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($count <= 1) {
                http_response_code(400);
                echo json_encode(['error' => 'Impossible de supprimer le dernier administrateur']);
                exit;
            }

            $stmt = $db->prepare('DELETE FROM admins WHERE id = :id');
            $stmt->execute([':id' => $id]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Type de compte invalide']);
            exit;
        }

        if ($stmt->rowCount() > 0) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Compte supprimé avec succès']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Compte non trouvé']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);
