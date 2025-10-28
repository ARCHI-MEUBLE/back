<?php
/**
 * Modèle Customer - Gestion des clients
 */

require_once __DIR__ . '/../core/Database.php';

class Customer {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Créer un nouveau client
     */
    public function create($email, $password, $firstName, $lastName, $phone = null, $address = null, $city = null, $postalCode = null, $country = null) {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $query = "INSERT INTO customers (email, password_hash, first_name, last_name, phone, address, city, postal_code, country)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$email, $passwordHash, $firstName, $lastName, $phone, $address, $city, $postalCode, $country]);

        return $this->db->lastInsertId();
    }

    /**
     * Récupérer un client par ID
     */
    public function getById($id) {
        $query = "SELECT id, email, first_name, last_name, phone, address, city, postal_code, country, created_at
                  FROM customers WHERE id = ?";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer un client par email
     */
    public function getByEmail($email) {
        $query = "SELECT id, email, password_hash, first_name, last_name, phone, address, city, postal_code, country, created_at
                  FROM customers WHERE email = ?";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$email]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifier les identifiants
     */
    public function verifyCredentials($email, $password) {
        $customer = $this->getByEmail($email);

        if (!$customer) {
            return false;
        }

        if (password_verify($password, $customer['password_hash'])) {
            // Ne pas retourner le hash du mot de passe
            unset($customer['password_hash']);
            return $customer;
        }

        return false;
    }

    /**
     * Mettre à jour le profil d'un client
     */
    public function update($id, $data) {
        $allowedFields = ['email', 'first_name', 'last_name', 'phone', 'address', 'city', 'postal_code', 'country'];
        $updates = [];
        $values = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $values[] = $id;
        $query = "UPDATE customers SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($values);
    }

    /**
     * Supprimer un client
     */
    public function delete($id) {
        $query = "DELETE FROM customers WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$id]);
    }

    /**
     * Vérifier si un email existe déjà
     */
    public function emailExists($email) {
        $query = "SELECT COUNT(*) FROM customers WHERE email = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }
}
