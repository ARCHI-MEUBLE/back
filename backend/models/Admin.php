<?php
/**
 * ArchiMeuble - Modèle Admin
 * Gère les opérations sur la table admins
 * Auteur : Collins
 * Date : 2025-10-21
 */

require_once __DIR__ . '/../core/Database.php';

class Admin {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
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
     * @return bool
     */
    public function create($email, $passwordHash) {
        $query = "INSERT INTO admins (email, password_hash)
                  VALUES (:email, :password_hash)";

        return $this->db->execute($query, [
            'email' => $email,
            'password_hash' => $passwordHash
        ]);
    }

    /**
     * Met à jour un admin
     * @param int $id
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
        $query = "UPDATE admins SET password_hash = :password_hash WHERE email = :email";
        return $this->db->execute($query, [
            'email' => $email,
            'password_hash' => $newPasswordHash
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

        if ($admin && password_verify($password, $admin['password'])) {
            // Retourner l'admin sans le hash du mot de passe
            unset($admin['password']);
            return $admin;
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
