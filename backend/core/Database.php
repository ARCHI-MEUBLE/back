<?php
/**
 * ArchiMeuble - Classe Database (Singleton)
 * Utilise PDO SQLite (compatible Docker)
 * Auteur : Collins
 * Date : 2025-10-20
 */

class Database {
    private static $instance = null;
    private $dbPath;
    private $pdo;

    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private function __construct() {
        // Vérifier si on est dans Docker ou en local
        // Production Railway: /data/archimeuble.db (volume persistant recommandé)
        // Local: /app/database/archimeuble.db
        $dbPath = getenv('DB_PATH');

        if (!$dbPath || empty($dbPath)) {
            // Priorité 1: Chemin standard Railway Volume
            if (file_exists('/data/archimeuble.db')) {
                $dbPath = '/data/archimeuble.db';
            } 
            // Priorité 2: Dossier database de l'application
            elseif (file_exists('/app/database/archimeuble.db')) {
                $dbPath = '/app/database/archimeuble.db';
            }
            // Priorité 3: Chemin relatif au projet
            else {
                $dbPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'archimeuble.db';
            }
        }

        $this->dbPath = $dbPath;
        error_log("Database: Loading SQLite DB from: " . $this->dbPath);

        if (!file_exists($this->dbPath)) {
            error_log("CRITICAL: Database file not found at " . $this->dbPath);
            // Tentative de création si le dossier existe
            $dir = dirname($this->dbPath);
            if (is_writable($dir)) {
                error_log("Database: Folder is writable, file will be created on first connection.");
            } else {
                throw new Exception("Base de données introuvable et dossier non scriptable : " . $this->dbPath);
            }
        }

        // Créer la connexion PDO
        try {
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Forcer l'encodage UTF-8 pour SQLite
            $this->pdo->exec("PRAGMA encoding = 'UTF-8'");

            // Vérifier et créer la table password_resets si elle n'existe pas
            $this->ensureTablesExist();
        } catch (PDOException $e) {
            error_log("Erreur de connexion PDO : " . $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données");
        }
    }

    /**
     * S'assure que les tables essentielles existent
     */
    private function ensureTablesExist() {
        try {
            // Création individuelle des tables pour garantir la compatibilité SQLite
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                token TEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS realisations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                titre TEXT NOT NULL,
                description TEXT,
                image_url TEXT,
                date_projet TEXT,
                categorie TEXT,
                lieu TEXT,
                dimensions TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS catalogue_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                category VARCHAR(100) NOT NULL,
                description TEXT,
                material VARCHAR(100),
                dimensions VARCHAR(100),
                unit_price DECIMAL(10,2) NOT NULL,
                unit VARCHAR(50) DEFAULT 'pièce',
                stock_quantity INTEGER DEFAULT 0,
                min_order_quantity INTEGER DEFAULT 1,
                is_available BOOLEAN DEFAULT 1,
                image_url VARCHAR(500),
                weight DECIMAL(8,2),
                tags TEXT,
                variation_label VARCHAR(100) DEFAULT 'Couleur / Finition',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS catalogue_item_variations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                catalogue_item_id INTEGER NOT NULL,
                color_name VARCHAR(100) NOT NULL,
                image_url VARCHAR(500) NOT NULL,
                is_default BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (catalogue_item_id) REFERENCES catalogue_items(id) ON DELETE CASCADE,
                UNIQUE(catalogue_item_id, color_name)
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS calendly_appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                calendly_event_id TEXT UNIQUE NOT NULL,
                client_name TEXT NOT NULL,
                client_email TEXT NOT NULL,
                event_type TEXT,
                start_time DATETIME NOT NULL,
                end_time DATETIME NOT NULL,
                timezone TEXT DEFAULT 'Europe/Paris',
                config_url TEXT,
                additional_notes TEXT,
                meeting_url TEXT,
                phone_number TEXT,
                status TEXT DEFAULT 'scheduled',
                confirmation_sent INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS payment_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                token TEXT NOT NULL UNIQUE,
                status TEXT DEFAULT 'active',
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                accessed_at DATETIME,
                paid_at DATETIME,
                created_by_admin TEXT,
                payment_type TEXT DEFAULT 'full',
                amount REAL,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS cart_catalogue_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                catalogue_item_id INTEGER NOT NULL,
                variation_id INTEGER,
                quantity INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                FOREIGN KEY (catalogue_item_id) REFERENCES catalogue_items(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS order_catalogue_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                catalogue_item_id INTEGER,
                variation_id INTEGER,
                product_name TEXT NOT NULL,
                variation_name TEXT,
                quantity INTEGER DEFAULT 1,
                unit_price REAL NOT NULL,
                total_price REAL NOT NULL,
                image_url TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS realisation_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                realisation_id INTEGER NOT NULL,
                image_url TEXT NOT NULL,
                ordre INTEGER DEFAULT 0,
                legende TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (realisation_id) REFERENCES realisations(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_realisation_images_realisation_id ON realisation_images(realisation_id)");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS facade_cart_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                config_data TEXT NOT NULL,
                quantity INTEGER DEFAULT 1,
                unit_price REAL NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS order_facade_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                config_data TEXT NOT NULL,
                quantity INTEGER DEFAULT 1,
                unit_price REAL NOT NULL,
                total_price REAL NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            )");

            // Vérifier et ajouter la colonne variation_label si elle manque (migration auto)
            try {
                $check = $this->pdo->query("PRAGMA table_info(catalogue_items)");
                $columns = $check->fetchAll(PDO::FETCH_COLUMN, 1);
                if (!in_array('variation_label', $columns)) {
                    $this->pdo->exec("ALTER TABLE catalogue_items ADD COLUMN variation_label VARCHAR(100) DEFAULT 'Couleur / Finition'");
                    error_log("Database: Added missing column variation_label to catalogue_items");
                }
            } catch (Exception $e) {
                // Ignorer si la table n'existe pas encore
            }

            // Migration auto pour payment_links
            try {
                $check = $this->pdo->query("PRAGMA table_info(payment_links)");
                $columns = $check->fetchAll(PDO::FETCH_COLUMN, 1);
                
                if (!empty($columns)) {
                    if (!in_array('token', $columns)) {
                        $this->pdo->exec("ALTER TABLE payment_links ADD COLUMN token TEXT");
                        error_log("Database: Added missing column token to payment_links");
                    }
                    if (!in_array('payment_type', $columns)) {
                        $this->pdo->exec("ALTER TABLE payment_links ADD COLUMN payment_type TEXT DEFAULT 'full'");
                        error_log("Database: Added missing column payment_type to payment_links");
                    }
                    if (!in_array('amount', $columns)) {
                        $this->pdo->exec("ALTER TABLE payment_links ADD COLUMN amount REAL");
                        error_log("Database: Added missing column amount to payment_links");
                    }
                    if (!in_array('created_by_admin', $columns)) {
                        $this->pdo->exec("ALTER TABLE payment_links ADD COLUMN created_by_admin TEXT");
                        error_log("Database: Added missing column created_by_admin to payment_links");
                    }
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (payment_links): " . $e->getMessage());
            }

            // Migration auto pour calendly_appointments
            try {
                $check = $this->pdo->query("PRAGMA table_info(calendly_appointments)");
                $columns = $check->fetchAll(PDO::FETCH_COLUMN, 1);
                
                if (!empty($columns)) {
                    if (!in_array('meeting_url', $columns)) {
                        $this->pdo->exec("ALTER TABLE calendly_appointments ADD COLUMN meeting_url TEXT");
                    }
                    if (!in_array('phone_number', $columns)) {
                        $this->pdo->exec("ALTER TABLE calendly_appointments ADD COLUMN phone_number TEXT");
                    }
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (calendly_appointments): " . $e->getMessage());
            }

        } catch (PDOException $e) {
            error_log("Erreur lors de la création des tables : " . $e->getMessage());
        }
    }

    /**
     * Empêche le clonage de l'instance
     */
    private function __clone() {}

    /**
     * Empêche la désérialisation de l'instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Retourne l'instance unique de la classe
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne l'instance PDO
     * @return PDO
     */
    public function getPDO() {
        return $this->pdo;
    }

    /**
     * Exécute une requête SELECT et retourne tous les résultats
     * @param string $query
     * @param array $params
     * @return array
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            error_log("✅ Query réussie, " . count($results) . " lignes retournées");
            return $results;
        } catch (PDOException $e) {
            error_log("❌❌❌ ERREUR DE REQUÊTE SQL ❌❌❌");
            error_log("Message: " . $e->getMessage());
            error_log("Query: " . $query);
            error_log("Params: " . print_r($params, true));
            throw $e; // Throw exception instead of returning empty array
        }
    }

    /**
     * Exécute une requête SELECT et retourne un seul résultat
     * @param string $query
     * @param array $params
     * @return array|null
     */
    public function queryOne($query, $params = []) {
        $results = $this->query($query, $params);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Exécute une requête INSERT, UPDATE ou DELETE
     * @param string $query
     * @param array $params
     * @return bool
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erreur d'exécution : " . $e->getMessage());
            throw $e; // Throw exception instead of returning false
        }
    }

    /**
     * Retourne l'ID du dernier enregistrement inséré
     * @return int
     */
    public function lastInsertId() {
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Démarre une transaction
     * @return bool
     */
    public function beginTransaction() {
        try {
            return $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            error_log("Erreur beginTransaction : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Valide une transaction
     * @return bool
     */
    public function commit() {
        try {
            return $this->pdo->commit();
        } catch (PDOException $e) {
            error_log("Erreur commit : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Annule une transaction
     * @return bool
     */
    public function rollback() {
        try {
            return $this->pdo->rollBack();
        } catch (PDOException $e) {
            error_log("Erreur rollback : " . $e->getMessage());
            return false;
        }
    }
}
//