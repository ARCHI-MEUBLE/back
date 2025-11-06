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

        $this->db->execute($query, [$email, $passwordHash, $firstName, $lastName, $phone, $address, $city, $postalCode, $country]);

        return $this->db->lastInsertId();
    }

    /**
     * Récupérer un client par ID
     */
    public function getById($id) {
        $query = "SELECT id, email, first_name, last_name, phone, address, city, postal_code, country, stripe_customer_id, created_at
                  FROM customers WHERE id = ?";

        return $this->db->queryOne($query, [$id]);
    }

    /**
     * Récupérer un client par email
     */
    public function getByEmail($email) {
        $query = "SELECT id, email, password_hash, first_name, last_name, phone, address, city, postal_code, country, created_at
                  FROM customers WHERE email = ?";

        return $this->db->queryOne($query, [$email]);
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

        return $this->db->execute($query, $values);
    }

    /**
     * Supprimer un client
     */
    public function delete($id) {
        $query = "DELETE FROM customers WHERE id = ?";
        return $this->db->execute($query, [$id]);
    }

    /**
     * Vérifier si un email existe déjà
     */
    public function emailExists($email) {
        $query = "SELECT COUNT(*) as count FROM customers WHERE email = ?";
        $result = $this->db->queryOne($query, [$email]);
        return $result && $result['count'] > 0;
    }

    /**
     * Mettre à jour le Stripe customer ID
     */
    public function updateStripeCustomerId($customerId, $stripeCustomerId) {
        $query = "UPDATE customers SET stripe_customer_id = ? WHERE id = ?";
        return $this->db->execute($query, [$stripeCustomerId, $customerId]);
    }
}
