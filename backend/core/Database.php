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

            // Migration auto pour categories
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL UNIQUE,
                    slug TEXT NOT NULL UNIQUE,
                    description TEXT,
                    image_url TEXT,
                    display_order INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                // Insertion des catégories par défaut si table vide
                $count = $this->pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
                if ($count == 0) {
                    $this->pdo->exec("INSERT INTO categories (name, slug, description, display_order, is_active) VALUES
                        ('Dressing', 'dressing', 'Optimisez chaque centimètre', 1, 1),
                        ('Bibliothèque', 'bibliotheque', 'Du sol au plafond', 2, 1),
                        ('Buffet', 'buffet', 'Élégance fonctionnelle', 3, 1),
                        ('Bureau', 'bureau', 'Votre espace de travail', 4, 1),
                        ('Meuble TV', 'meuble-tv', 'Lignes épurées', 5, 1),
                        ('Sous-escalier', 'sous-escalier', 'Chaque recoin optimisé', 6, 1),
                        ('Tête de lit', 'tete-de-lit', 'Confort et style', 7, 1)
                    ");
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (categories): " . $e->getMessage());
            }

            // Migration auto pour quote_requests
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS quote_requests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    first_name TEXT NOT NULL,
                    last_name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    phone TEXT NOT NULL,
                    description TEXT,
                    status TEXT NOT NULL DEFAULT 'pending',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                $this->pdo->exec("CREATE TABLE IF NOT EXISTS quote_request_files (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    quote_request_id INTEGER NOT NULL,
                    file_name TEXT NOT NULL,
                    file_path TEXT NOT NULL,
                    file_type TEXT NOT NULL,
                    file_size INTEGER NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (quote_request_id) REFERENCES quote_requests(id) ON DELETE CASCADE
                )");

                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_quote_requests_status ON quote_requests(status)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_quote_requests_created ON quote_requests(created_at DESC)");
            } catch (Exception $e) {
                error_log("Database Migration Error (quote_requests): " . $e->getMessage());
            }

            // Migration auto pour sample orders
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS cart_sample_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    customer_id INTEGER NOT NULL,
                    sample_color_id INTEGER NOT NULL,
                    quantity INTEGER NOT NULL DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                    FOREIGN KEY (sample_color_id) REFERENCES sample_colors(id) ON DELETE CASCADE,
                    UNIQUE(customer_id, sample_color_id)
                )");

                $this->pdo->exec("CREATE TABLE IF NOT EXISTS order_sample_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    sample_color_id INTEGER NOT NULL,
                    sample_name VARCHAR(255) NOT NULL,
                    sample_type_name VARCHAR(255),
                    material VARCHAR(255),
                    image_url TEXT,
                    hex VARCHAR(20),
                    quantity INTEGER NOT NULL DEFAULT 1,
                    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                    FOREIGN KEY (sample_color_id) REFERENCES sample_colors(id) ON DELETE SET NULL
                )");

                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cart_sample_items_customer ON cart_sample_items(customer_id)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cart_sample_items_color ON cart_sample_items(sample_color_id)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_sample_items_order ON order_sample_items(order_id)");
            } catch (Exception $e) {
                error_log("Database Migration Error (sample_orders): " . $e->getMessage());
            }

            // Migration auto pour payment_installments
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS payment_installments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    customer_id INTEGER NOT NULL,
                    installment_number INTEGER NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    due_date DATE NOT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    stripe_payment_intent_id TEXT,
                    paid_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (order_id) REFERENCES orders(id),
                    FOREIGN KEY (customer_id) REFERENCES customers(id)
                )");

                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_installments_order ON payment_installments(order_id)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_installments_customer ON payment_installments(customer_id)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_installments_due_date ON payment_installments(due_date)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_installments_status ON payment_installments(status)");
            } catch (Exception $e) {
                error_log("Database Migration Error (payment_installments): " . $e->getMessage());
            }

            // Migration auto pour models (hover_image_url)
            try {
                $check = $this->pdo->query("PRAGMA table_info(models)");
                $columns = $check->fetchAll(PDO::FETCH_COLUMN, 1);

                if (!empty($columns) && !in_array('hover_image_url', $columns)) {
                    $this->pdo->exec("ALTER TABLE models ADD COLUMN hover_image_url TEXT");
                    error_log("Database: Added missing column hover_image_url to models");
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (models hover_image_url): " . $e->getMessage());
            }

            // Migration auto pour pricing_config
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS pricing_config (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    category TEXT NOT NULL,
                    item_type TEXT NOT NULL,
                    param_name TEXT NOT NULL,
                    param_value REAL NOT NULL,
                    unit TEXT NOT NULL,
                    description TEXT,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(category, item_type, param_name)
                )");

                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_pricing_config_category ON pricing_config(category)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_pricing_config_active ON pricing_config(is_active)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_pricing_config_lookup ON pricing_config(category, item_type, param_name)");
                
                // Vérifier si la table est vide pour insérer les données par défaut
                $count = $this->pdo->query("SELECT COUNT(*) FROM pricing_config")->fetchColumn();
                if ($count == 0) {
                    error_log("Database: pricing_config table is empty, inserting default values...");
                    $this->pdo->exec("
                        INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
                        -- Matériau de base (UN SEUL pour tous les meubles)
                        ('materials', 'base', 'supplement', 0, 'eur', 'Supplément matériau (0 par défaut)'),
                        ('materials', 'base', 'price_per_m2', 50, 'eur_m2', 'Prix du matériau de base au m²'),

                        -- Tiroirs
                        ('drawers', 'standard', 'base_price', 35, 'eur', 'Prix de base d''un tiroir standard'),
                        ('drawers', 'standard', 'coefficient', 0.0001, 'coefficient', 'Coefficient (× largeur × profondeur en mm²)'),
                        ('drawers', 'push', 'base_price', 45, 'eur', 'Prix de base d''un tiroir push'),
                        ('drawers', 'push', 'coefficient', 0.0001, 'coefficient', 'Coefficient (× largeur × profondeur en mm²)'),

                        -- Étagères
                        ('shelves', 'glass', 'price_per_m2', 250, 'eur_m2', 'Prix du verre au m²'),
                        ('shelves', 'standard', 'price_per_m2', 100, 'eur_m2', 'Prix au m² d''une étagère standard'),

                        -- Éclairage LED
                        ('lighting', 'led', 'price_per_linear_meter', 15, 'eur_linear_m', 'Prix de la LED par mètre linéaire'),

                        -- Passe-câbles
                        ('cables', 'pass_cable', 'fixed_price', 10, 'eur', 'Prix fixe pour un passe-câble'),

                        -- Socles
                        ('bases', 'none', 'fixed_price', 0, 'eur', 'Pas de socle'),
                        ('bases', 'wood', 'price_per_m3', 800, 'eur_m3', 'Prix du bois massif par m³'),
                        ('bases', 'wood', 'height', 80, 'mm', 'Hauteur FIXE du socle bois (même pour tous les meubles)'),
                        ('bases', 'metal', 'price_per_foot', 20, 'eur', 'Prix d''un pied métallique'),
                        ('bases', 'metal', 'foot_interval', 2000, 'mm', 'Intervalle entre pieds (2m)'),

                        -- Charnières
                        ('hinges', 'standard', 'price_per_unit', 5, 'eur', 'Prix d''une charnière standard'),

                        -- Portes
                        ('doors', 'simple', 'coefficient', 0.00004, 'coefficient', 'Coefficient porte simple'),
                        ('doors', 'simple', 'hinge_count', 2, 'units', 'Nb charnières porte simple'),
                        ('doors', 'double', 'coefficient', 0.00008, 'coefficient', 'Coefficient double porte'),
                        ('doors', 'double', 'hinge_count', 4, 'units', 'Nb charnières double porte'),
                        ('doors', 'glass', 'coefficient', 0.00009, 'coefficient', 'Coefficient porte vitrée'),
                        ('doors', 'glass', 'hinge_count', 2, 'units', 'Nb charnières porte vitrée'),
                        ('doors', 'push', 'coefficient', 0.00005, 'coefficient', 'Coefficient porte push'),
                        ('doors', 'push', 'hinge_count', 2, 'units', 'Nb charnières porte push'),

                        -- Colonnes
                        ('columns', 'standard', 'price_per_m2', 120, 'eur_m2', 'Prix au m² d''une colonne'),

                        -- Penderie
                        ('wardrobe', 'rod', 'price_per_linear_meter', 20, 'eur_linear_m', 'Prix de la barre de penderie par mètre linéaire'),

                        -- Poignées
                        ('handles', 'horizontal_bar', 'price_per_unit', 15, 'eur', 'Prix d''une barre horizontale'),
                        ('handles', 'vertical_bar', 'price_per_unit', 15, 'eur', 'Prix d''une barre verticale'),
                        ('handles', 'knob', 'price_per_unit', 10, 'eur', 'Prix d''un bouton'),
                        ('handles', 'recessed', 'price_per_unit', 12, 'eur', 'Prix d''une poignée encastrée'),

                        -- Caisson
                        ('casing', 'full', 'coefficient', 1.2, 'coefficient', 'Coefficient caisson complet'),

                        -- Affichage des prix
                        ('display', 'price', 'display_mode', 0, 'units', 'Mode d''affichage (0: DIRECT, 1: INTERVALLE)'),
                        ('display', 'price', 'deviation_range', 100, 'eur', 'Écart type pour l''intervalle')
                    ");
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (pricing_config): " . $e->getMessage());
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