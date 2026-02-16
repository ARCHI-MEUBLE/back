<?php
/**
 * ArchiMeuble - Classe Database (Singleton)
 * Utilise PDO PostgreSQL
 * Auteur : Collins
 * Date : 2025-10-20
 */

class Database {
    private static $instance = null;
    private $pdo;

    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private function __construct() {
        $databaseUrl = getenv('DATABASE_URL');

        if (!$databaseUrl || empty($databaseUrl)) {
            throw new Exception("DATABASE_URL environment variable is not set");
        }

        error_log("Database: Connecting to PostgreSQL...");

        // Parser DATABASE_URL
        $params = parse_url($databaseUrl);
        $host = $params['host'] ?? 'localhost';
        $port = $params['port'] ?? 5432;
        $dbname = ltrim($params['path'] ?? '/archimeuble', '/');
        $user = $params['user'] ?? 'archimeuble';
        $pass = $params['pass'] ?? '';

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

        try {
            $this->pdo = new PDO($dsn, $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->ensureTablesExist();
        } catch (PDOException $e) {
            error_log("Erreur de connexion PDO : " . $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données");
        }
    }

    /**
     * Retourne les colonnes d'une table via information_schema
     */
    private function getTableColumns(string $tableName): array {
        $stmt = $this->pdo->prepare(
            "SELECT column_name FROM information_schema.columns WHERE table_name = :table AND table_schema = 'public'"
        );
        $stmt->execute(['table' => $tableName]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * S'assure que les tables essentielles existent
     */
    private function ensureTablesExist() {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id SERIAL PRIMARY KEY,
                email TEXT NOT NULL,
                token TEXT NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS realisations (
                id SERIAL PRIMARY KEY,
                titre TEXT NOT NULL,
                description TEXT,
                image_url TEXT,
                date_projet TEXT,
                categorie TEXT,
                lieu TEXT,
                dimensions TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS catalogue_items (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                category VARCHAR(100) NOT NULL,
                description TEXT,
                material VARCHAR(100),
                dimensions VARCHAR(100),
                unit_price DECIMAL(10,2) NOT NULL,
                unit VARCHAR(50) DEFAULT 'pièce',
                stock_quantity INTEGER DEFAULT 0,
                min_order_quantity INTEGER DEFAULT 1,
                is_available BOOLEAN DEFAULT TRUE,
                image_url VARCHAR(500),
                weight DECIMAL(8,2),
                tags TEXT,
                variation_label VARCHAR(100) DEFAULT 'Couleur / Finition',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS catalogue_item_variations (
                id SERIAL PRIMARY KEY,
                catalogue_item_id INTEGER NOT NULL,
                color_name VARCHAR(100) NOT NULL,
                image_url VARCHAR(500) NOT NULL,
                is_default BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (catalogue_item_id) REFERENCES catalogue_items(id) ON DELETE CASCADE,
                UNIQUE(catalogue_item_id, color_name)
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS calendly_appointments (
                id SERIAL PRIMARY KEY,
                calendly_event_id TEXT UNIQUE NOT NULL,
                client_name TEXT NOT NULL,
                client_email TEXT NOT NULL,
                event_type TEXT,
                start_time TIMESTAMP NOT NULL,
                end_time TIMESTAMP NOT NULL,
                timezone TEXT DEFAULT 'Europe/Paris',
                config_url TEXT,
                additional_notes TEXT,
                meeting_url TEXT,
                phone_number TEXT,
                status TEXT DEFAULT 'scheduled',
                confirmation_sent BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS payment_links (
                id SERIAL PRIMARY KEY,
                order_id INTEGER NOT NULL,
                token TEXT NOT NULL UNIQUE,
                status TEXT DEFAULT 'active',
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                accessed_at TIMESTAMP,
                paid_at TIMESTAMP,
                created_by_admin TEXT,
                payment_type TEXT DEFAULT 'full',
                amount DECIMAL(10,2),
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS cart_catalogue_items (
                id SERIAL PRIMARY KEY,
                customer_id INTEGER NOT NULL,
                catalogue_item_id INTEGER NOT NULL,
                variation_id INTEGER,
                quantity INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                FOREIGN KEY (catalogue_item_id) REFERENCES catalogue_items(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS order_catalogue_items (
                id SERIAL PRIMARY KEY,
                order_id INTEGER NOT NULL,
                catalogue_item_id INTEGER,
                variation_id INTEGER,
                product_name TEXT NOT NULL,
                variation_name TEXT,
                quantity INTEGER DEFAULT 1,
                unit_price DECIMAL(10,2) NOT NULL,
                total_price DECIMAL(10,2) NOT NULL,
                image_url TEXT,
                name TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS realisation_images (
                id SERIAL PRIMARY KEY,
                realisation_id INTEGER NOT NULL,
                image_url TEXT NOT NULL,
                ordre INTEGER DEFAULT 0,
                legende TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (realisation_id) REFERENCES realisations(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_realisation_images_realisation_id ON realisation_images(realisation_id)");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS facade_cart_items (
                id SERIAL PRIMARY KEY,
                customer_id INTEGER NOT NULL,
                config_data TEXT NOT NULL,
                quantity INTEGER DEFAULT 1,
                unit_price DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS order_facade_items (
                id SERIAL PRIMARY KEY,
                order_id INTEGER NOT NULL,
                config_data TEXT NOT NULL,
                quantity INTEGER DEFAULT 1,
                unit_price DECIMAL(10,2) NOT NULL,
                total_price DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            )");

            // Migration auto : vérifier colonnes manquantes via information_schema
            try {
                $columns = $this->getTableColumns('catalogue_items');
                if (!empty($columns) && !in_array('variation_label', $columns)) {
                    $this->pdo->exec("ALTER TABLE catalogue_items ADD COLUMN variation_label VARCHAR(100) DEFAULT 'Couleur / Finition'");
                    error_log("Database: Added missing column variation_label to catalogue_items");
                }
            } catch (Exception $e) {
                // Ignorer si la table n'existe pas encore
            }

            // Migration auto pour payment_links
            try {
                $columns = $this->getTableColumns('payment_links');
                if (!empty($columns)) {
                    if (!in_array('token', $columns)) {
                        $this->pdo->exec("ALTER TABLE payment_links ADD COLUMN token TEXT");
                    }
                    if (!in_array('payment_type', $columns)) {
                        $this->pdo->exec("ALTER TABLE payment_links ADD COLUMN payment_type TEXT DEFAULT 'full'");
                    }
                    if (!in_array('amount', $columns)) {
                        $this->pdo->exec("ALTER TABLE payment_links ADD COLUMN amount DECIMAL(10,2)");
                    }
                    if (!in_array('created_by_admin', $columns)) {
                        $this->pdo->exec("ALTER TABLE payment_links ADD COLUMN created_by_admin TEXT");
                    }
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (payment_links): " . $e->getMessage());
            }

            // Migration auto pour calendly_appointments
            try {
                $columns = $this->getTableColumns('calendly_appointments');
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
                    id SERIAL PRIMARY KEY,
                    name TEXT NOT NULL UNIQUE,
                    slug TEXT NOT NULL UNIQUE,
                    description TEXT,
                    image_url TEXT,
                    display_order INTEGER DEFAULT 0,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");

                $count = $this->pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
                if ($count == 0) {
                    $this->pdo->exec("INSERT INTO categories (name, slug, description, display_order, is_active) VALUES
                        ('Dressing', 'dressing', 'Optimisez chaque centimètre', 1, TRUE),
                        ('Bibliothèque', 'bibliotheque', 'Du sol au plafond', 2, TRUE),
                        ('Buffet', 'buffet', 'Élégance fonctionnelle', 3, TRUE),
                        ('Bureau', 'bureau', 'Votre espace de travail', 4, TRUE),
                        ('Meuble TV', 'meuble-tv', 'Lignes épurées', 5, TRUE),
                        ('Sous-escalier', 'sous-escalier', 'Chaque recoin optimisé', 6, TRUE),
                        ('Tête de lit', 'tete-de-lit', 'Confort et style', 7, TRUE)
                    ");
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (categories): " . $e->getMessage());
            }

            // Migration auto pour quote_requests
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS quote_requests (
                    id SERIAL PRIMARY KEY,
                    first_name TEXT NOT NULL,
                    last_name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    phone TEXT NOT NULL,
                    description TEXT,
                    status TEXT NOT NULL DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");

                $this->pdo->exec("CREATE TABLE IF NOT EXISTS quote_request_files (
                    id SERIAL PRIMARY KEY,
                    quote_request_id INTEGER NOT NULL,
                    file_name TEXT NOT NULL,
                    file_path TEXT NOT NULL,
                    file_type TEXT NOT NULL,
                    file_size INTEGER NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
                    id SERIAL PRIMARY KEY,
                    customer_id INTEGER NOT NULL,
                    sample_color_id INTEGER NOT NULL,
                    quantity INTEGER NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                    FOREIGN KEY (sample_color_id) REFERENCES sample_colors(id) ON DELETE CASCADE,
                    UNIQUE(customer_id, sample_color_id)
                )");

                $this->pdo->exec("CREATE TABLE IF NOT EXISTS order_sample_items (
                    id SERIAL PRIMARY KEY,
                    order_id INTEGER NOT NULL,
                    sample_color_id INTEGER NOT NULL,
                    sample_name VARCHAR(255) NOT NULL,
                    sample_type_name VARCHAR(255),
                    material VARCHAR(255),
                    image_url TEXT,
                    hex VARCHAR(20),
                    quantity INTEGER NOT NULL DEFAULT 1,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
                    id SERIAL PRIMARY KEY,
                    order_id INTEGER NOT NULL,
                    customer_id INTEGER NOT NULL,
                    installment_number INTEGER NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    due_date DATE NOT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    stripe_payment_intent_id TEXT,
                    paid_at TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
                $columns = $this->getTableColumns('models');
                if (!empty($columns) && !in_array('hover_image_url', $columns)) {
                    $this->pdo->exec("ALTER TABLE models ADD COLUMN hover_image_url TEXT");
                    error_log("Database: Added missing column hover_image_url to models");
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (models hover_image_url): " . $e->getMessage());
            }

            // Migration auto pour order_catalogue_items (ajouter colonne 'name' si manquante)
            try {
                $columns = $this->getTableColumns('order_catalogue_items');
                if (!empty($columns) && !in_array('name', $columns)) {
                    $this->pdo->exec("ALTER TABLE order_catalogue_items ADD COLUMN name TEXT");
                    error_log("Database: Added missing column 'name' to order_catalogue_items");
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (order_catalogue_items name): " . $e->getMessage());
            }

            // Migration auto pour orders (ajouter colonne 'confirmation_email_sent' si manquante)
            try {
                $columns = $this->getTableColumns('orders');
                if (!empty($columns) && !in_array('confirmation_email_sent', $columns)) {
                    $this->pdo->exec("ALTER TABLE orders ADD COLUMN confirmation_email_sent BOOLEAN DEFAULT FALSE");
                    error_log("Database: Added missing column 'confirmation_email_sent' to orders");
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (orders confirmation_email_sent): " . $e->getMessage());
            }

            // Migration auto pour email_verifications
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_verifications (
                    id SERIAL PRIMARY KEY,
                    email TEXT NOT NULL,
                    code TEXT NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    used BOOLEAN DEFAULT FALSE
                )");

                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_verifications_email ON email_verifications(email)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_verifications_expires ON email_verifications(expires_at)");

                $columns = $this->getTableColumns('customers');
                if (!empty($columns) && !in_array('email_verified', $columns)) {
                    $this->pdo->exec("ALTER TABLE customers ADD COLUMN email_verified BOOLEAN DEFAULT FALSE");
                    $this->pdo->exec("UPDATE customers SET email_verified = TRUE");
                    error_log("Database: Added email_verified column to customers and marked existing as verified");
                }
            } catch (Exception $e) {
                error_log("Database Migration Error (email_verifications): " . $e->getMessage());
            }

            // Migration auto pour pricing_config
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS pricing_config (
                    id SERIAL PRIMARY KEY,
                    category TEXT NOT NULL,
                    item_type TEXT NOT NULL,
                    param_name TEXT NOT NULL,
                    param_value DECIMAL(10,4) NOT NULL,
                    unit TEXT NOT NULL,
                    description TEXT,
                    is_active BOOLEAN NOT NULL DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(category, item_type, param_name)
                )");

                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_pricing_config_category ON pricing_config(category)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_pricing_config_active ON pricing_config(is_active)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_pricing_config_lookup ON pricing_config(category, item_type, param_name)");

                $count = $this->pdo->query("SELECT COUNT(*) FROM pricing_config")->fetchColumn();
                if ($count == 0) {
                    error_log("Database: pricing_config table is empty, inserting default values...");
                    $this->pdo->exec("
                        INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
                        ('materials', 'base', 'supplement', 0, 'eur', 'Supplément matériau (0 par défaut)'),
                        ('materials', 'base', 'price_per_m2', 50, 'eur_m2', 'Prix du matériau de base au m²'),
                        ('drawers', 'standard', 'base_price', 35, 'eur', 'Prix de base d''un tiroir standard'),
                        ('drawers', 'standard', 'coefficient', 0.0001, 'coefficient', 'Coefficient (× largeur × profondeur en mm²)'),
                        ('drawers', 'push', 'base_price', 45, 'eur', 'Prix de base d''un tiroir push'),
                        ('drawers', 'push', 'coefficient', 0.0001, 'coefficient', 'Coefficient (× largeur × profondeur en mm²)'),
                        ('shelves', 'glass', 'price_per_m2', 250, 'eur_m2', 'Prix du verre au m²'),
                        ('shelves', 'standard', 'price_per_m2', 100, 'eur_m2', 'Prix au m² d''une étagère standard'),
                        ('lighting', 'led', 'price_per_linear_meter', 15, 'eur_linear_m', 'Prix de la LED par mètre linéaire'),
                        ('cables', 'pass_cable', 'fixed_price', 10, 'eur', 'Prix fixe pour un passe-câble'),
                        ('bases', 'none', 'fixed_price', 0, 'eur', 'Pas de socle'),
                        ('bases', 'wood', 'price_per_m3', 800, 'eur_m3', 'Prix du bois massif par m³'),
                        ('bases', 'wood', 'height', 80, 'mm', 'Hauteur FIXE du socle bois (même pour tous les meubles)'),
                        ('bases', 'metal', 'price_per_foot', 20, 'eur', 'Prix d''un pied métallique'),
                        ('bases', 'metal', 'foot_interval', 2000, 'mm', 'Intervalle entre pieds (2m)'),
                        ('hinges', 'standard', 'price_per_unit', 5, 'eur', 'Prix d''une charnière standard'),
                        ('doors', 'simple', 'coefficient', 0.00004, 'coefficient', 'Coefficient porte simple'),
                        ('doors', 'simple', 'hinge_count', 2, 'units', 'Nb charnières porte simple'),
                        ('doors', 'double', 'coefficient', 0.00008, 'coefficient', 'Coefficient double porte'),
                        ('doors', 'double', 'hinge_count', 4, 'units', 'Nb charnières double porte'),
                        ('doors', 'glass', 'coefficient', 0.00009, 'coefficient', 'Coefficient porte vitrée'),
                        ('doors', 'glass', 'hinge_count', 2, 'units', 'Nb charnières porte vitrée'),
                        ('doors', 'push', 'coefficient', 0.00005, 'coefficient', 'Coefficient porte push'),
                        ('doors', 'push', 'hinge_count', 2, 'units', 'Nb charnières porte push'),
                        ('columns', 'standard', 'price_per_m2', 120, 'eur_m2', 'Prix au m² d''une colonne'),
                        ('wardrobe', 'rod', 'price_per_linear_meter', 20, 'eur_linear_m', 'Prix de la barre de penderie par mètre linéaire'),
                        ('handles', 'horizontal_bar', 'price_per_unit', 15, 'eur', 'Prix d''une barre horizontale'),
                        ('handles', 'vertical_bar', 'price_per_unit', 15, 'eur', 'Prix d''une barre verticale'),
                        ('handles', 'knob', 'price_per_unit', 10, 'eur', 'Prix d''un bouton'),
                        ('handles', 'recessed', 'price_per_unit', 12, 'eur', 'Prix d''une poignée encastrée'),
                        ('casing', 'full', 'coefficient', 1.2, 'coefficient', 'Coefficient caisson complet'),
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
            error_log("Query OK, " . count($results) . " rows");
            return $results;
        } catch (PDOException $e) {
            error_log("SQL ERROR: " . $e->getMessage());
            error_log("Query: " . $query);
            error_log("Params: " . print_r($params, true));
            throw $e;
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
            throw $e;
        }
    }

    /**
     * Retourne l'ID du dernier enregistrement inséré
     * @param string|null $sequenceName Nom de la séquence PostgreSQL (ex: 'tablename_id_seq')
     * @return int
     */
    public function lastInsertId($sequenceName = null) {
        return (int)$this->pdo->lastInsertId($sequenceName);
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
