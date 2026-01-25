<?php
/**
 * ArchiMeuble - Modèle User
 * Gère les opérations sur la table users
 * Auteur : Collins
 * Date : 2025-10-21
 *
 * SÉCURITÉ: Liste blanche des colonnes pour prévenir l'injection SQL
 */

require_once __DIR__ . '/../core/Database.php';

class User {
    private $db;

    /**
     * SÉCURITÉ: Liste blanche des colonnes autorisées pour update()
     * Seules ces colonnes peuvent être modifiées via la méthode update()
     */
    private const ALLOWED_UPDATE_COLUMNS = [
        'email',
        'name',
        'updated_at',
        // NE PAS INCLURE: 'password_hash', 'id', 'created_at'
        // Le mot de passe doit être modifié via une méthode dédiée
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère tous les utilisateurs
     * @return array
     */
    public function getAll() {
        $query = "SELECT id, email, name, created_at FROM users ORDER BY created_at DESC";
        return $this->db->query($query);
    }

    /**
     * Récupère un utilisateur par son ID
     * @param string $id
     * @return array|null
     */
    public function getById($id) {
        $query = "SELECT id, email, name, created_at FROM users WHERE id = :id";
        return $this->db->queryOne($query, ['id' => $id]);
    }

    /**
     * Récupère un utilisateur par son email
     * @param string $email
     * @return array|null
     */
    public function getByEmail($email) {
        $query = "SELECT * FROM users WHERE email = :email";
        return $this->db->queryOne($query, ['email' => $email]);
    }

    /**
     * Crée un nouvel utilisateur
     * @param string $id
     * @param string $email
     * @param string $passwordHash
     * @param string|null $name
     * @return bool
     */
    public function create($id, $email, $passwordHash, $name = null) {
        $query = "INSERT INTO users (id, email, password_hash, name)
                  VALUES (:id, :email, :password_hash, :name)";

        return $this->db->execute($query, [
            'id' => $id,
            'email' => $email,
            'password_hash' => $passwordHash,
            'name' => $name
        ]);
    }

    /**
     * Met à jour un utilisateur
     * SÉCURITÉ: Seules les colonnes de la liste blanche peuvent être modifiées
     *
     * @param string $id
     * @param array $data
     * @return bool
     * @throws InvalidArgumentException si une colonne non autorisée est fournie
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            // SÉCURITÉ: Vérifier que la colonne est dans la liste blanche
            if (!in_array($key, self::ALLOWED_UPDATE_COLUMNS, true)) {
                // Log la tentative suspecte
                error_log("[SECURITY] Tentative de modification de colonne non autorisée: '$key' pour user ID: $id");
                // Option 1: Ignorer silencieusement la colonne
                continue;
                // Option 2: Lever une exception (décommenter si préféré)
                // throw new InvalidArgumentException("Colonne '$key' non autorisée pour la mise à jour");
            }

            $fields[] = "$key = :$key";
            $params[$key] = $value;
        }

        // Si aucun champ valide, ne rien faire
        if (empty($fields)) {
            return false;
        }

        $query = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($query, $params);
    }

    /**
     * Met à jour le mot de passe d'un utilisateur
     * SÉCURITÉ: Méthode dédiée pour le changement de mot de passe
     *
     * @param string $id
     * @param string $newPasswordHash - Doit être déjà hashé avec password_hash()
     * @return bool
     */
    public function updatePassword($id, $newPasswordHash) {
        $query = "UPDATE users SET password_hash = :password_hash WHERE id = :id";
        return $this->db->execute($query, [
            'id' => $id,
            'password_hash' => $newPasswordHash
        ]);
    }

    /**
     * Supprime un utilisateur
     * @param string $id
     * @return bool
     */
    public function delete($id) {
        $query = "DELETE FROM users WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }

    /**
     * Vérifie si un email existe déjà
     * @param string $email
     * @return bool
     */
    public function emailExists($email) {
        $query = "SELECT COUNT(*) as count FROM users WHERE email = :email";
        $result = $this->db->queryOne($query, ['email' => $email]);
        return $result && (int)$result['count'] > 0;
    }

    /**
     * Vérifie les identifiants d'un utilisateur
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function verifyCredentials($email, $password) {
        $user = $this->getByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Retourner l'utilisateur sans le hash du mot de passe
            unset($user['password_hash']);
            return $user;
        }

        return null;
    }

    /**
     * Compte le nombre total d'utilisateurs
     * @return int
     */
    public function count() {
        $query = "SELECT COUNT(*) as total FROM users";
        $result = $this->db->queryOne($query);
        return $result ? (int)$result['total'] : 0;
    }
}
