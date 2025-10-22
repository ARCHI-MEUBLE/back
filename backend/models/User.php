<?php
/**
 * ArchiMeuble - Modèle User
 * Gère les opérations sur la table users
 * Auteur : Collins
 * Date : 2025-10-21
 */

require_once __DIR__ . '/../core/Database.php';

class User {
    private $db;

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
     * @param string $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $params[$key] = $value;
        }

        $query = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($query, $params);
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
