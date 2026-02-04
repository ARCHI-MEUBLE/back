<?php
/**
 * ArchiMeuble - Modèle Admin
 * Gère les opérations sur la table admins
 * Auteur : Collins
 * Date : 2025-10-21
 *
 * SÉCURITÉ: Liste blanche des colonnes pour prévenir l'injection SQL
 */

require_once __DIR__ . '/../core/Database.php';

class Admin {
    private $db;

    /**
     * SÉCURITÉ: Liste blanche des colonnes autorisées pour update()
     * Seules ces colonnes peuvent être modifiées via la méthode update()
     */
    private const ALLOWED_UPDATE_COLUMNS = [
        'username',
        'email',
        'updated_at',
        // NE PAS INCLURE: 'password', 'password_hash', 'id', 'created_at'
        // Le mot de passe doit être modifié via updatePassword()
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Vérifie si une colonne existe dans une table
     */
    private function columnExists($table, $column) {
        try {
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = :table AND table_schema = 'public'");
            $stmt->execute(['table' => $table]);
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            return in_array($column, $columns);
        } catch (\Throwable $e) {
            // ignore
        }
        return false;
    }

    /**
     * Récupère tous les administrateurs
     * @return array
     */
    public function getAll() {
        $query = "SELECT id, username, email, created_at FROM admins ORDER BY created_at DESC";
        return $this->db->query($query);
    }

    /**
     * Récupère un admin par son email
     * @param string $email
     * @return array|null
     */
    public function getByEmail($email) {
        $query = "SELECT * FROM admins WHERE email = :email OR username = :username";
        return $this->db->queryOne($query, ['email' => $email, 'username' => $email]);
    }

    /**
     * Crée un nouvel administrateur
     * @param string $email
     * @param string $passwordHash
     * @param string $username (optionnel, généré depuis email si non fourni)
     * @return bool
     */
    public function create($email, $passwordHash, $username = null) {
        // Si username n'est pas fourni, utiliser la partie avant @ de l'email
        if ($username === null) {
            $username = explode('@', $email)[0];
        }

        $query = "INSERT INTO admins (username, email, password)
                  VALUES (:username, :email, :password)";

        return $this->db->execute($query, [
            'username' => $username,
            'email' => $email,
            'password' => $passwordHash
        ]);
    }

    /**
     * Met à jour un admin
     * SÉCURITÉ: Seules les colonnes de la liste blanche peuvent être modifiées
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            // SÉCURITÉ: Vérifier que la colonne est dans la liste blanche
            if (!in_array($key, self::ALLOWED_UPDATE_COLUMNS, true)) {
                // Log la tentative suspecte
                error_log("[SECURITY] Tentative de modification de colonne non autorisée: '$key' pour admin ID: $id");
                // Ignorer silencieusement la colonne non autorisée
                continue;
            }

            $fields[] = "$key = :$key";
            $params[$key] = $value;
        }

        // Si aucun champ valide, ne rien faire
        if (empty($fields)) {
            return false;
        }

        $query = "UPDATE admins SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($query, $params);
    }

    /**
     * Met à jour le mot de passe d'un admin
     * @param string $email
     * @param string $newPasswordHash
     * @return bool
     */
    public function updatePassword($email, $newPasswordHash) {
        // Support des deux schémas: 'password_hash' (nouveau) ou 'password' (ancien)
        if ($this->columnExists('admins', 'password_hash')) {
            $query = "UPDATE admins SET password_hash = :password WHERE email = :email OR username = :email";
        } else {
            $query = "UPDATE admins SET password = :password WHERE email = :email OR username = :email";
        }
        return $this->db->execute($query, [
            'email' => $email,
            'password' => $newPasswordHash
        ]);
    }

    /**
     * Supprime un administrateur
     * @param string $email
     * @return bool
     */
    public function delete($email) {
        $query = "DELETE FROM admins WHERE email = :email";
        return $this->db->execute($query, ['email' => $email]);
    }

    /**
     * Vérifie si un email existe déjà
     * @param string $email
     * @return bool
     */
    public function emailExists($email) {
        $query = "SELECT COUNT(*) as count FROM admins WHERE email = :email";
        $result = $this->db->queryOne($query, ['email' => $email]);
        return $result && (int)$result['count'] > 0;
    }

    /**
     * Vérifie les identifiants d'un administrateur
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function verifyCredentials($email, $password) {
        $admin = $this->getByEmail($email);

        if (!$admin) {
            return null;
        }

        // Gérer les deux schémas: 'password_hash' (nouveau) ou 'password' (ancien)
        if ($admin && isset($admin['password_hash'])) {
            if (password_verify($password, $admin['password_hash'])) {
                // Retirer le hash du mot de passe avant de renvoyer
                unset($admin['password_hash']);
                return $admin;
            }
        } elseif ($admin && isset($admin['password'])) {
            if (password_verify($password, $admin['password'])) {
                unset($admin['password']);
                return $admin;
            }
        }

        return null;
    }

    /**
     * Compte le nombre total d'administrateurs
     * @return int
     */
    public function count() {
        $query = "SELECT COUNT(*) as total FROM admins";
        $result = $this->db->queryOne($query);
        return $result ? (int)$result['total'] : 0;
    }
}
