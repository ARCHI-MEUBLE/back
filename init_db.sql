-- =============================================================================
-- ArchiMeuble - Initialisation PostgreSQL
-- Ce fichier crée le schéma de base de données complet
-- =============================================================================

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    name TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des administrateurs
CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    email TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des modèles/templates de meubles
CREATE TABLE IF NOT EXISTS models (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    prompt TEXT NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.0,
    image_url TEXT,
    category TEXT,
    config_data TEXT,
    hover_image_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des configurations utilisateur
CREATE TABLE IF NOT EXISTS configurations (
    id SERIAL PRIMARY KEY,
    user_id TEXT,
    user_session TEXT,
    template_id INTEGER,
    config_string TEXT,
    prompt TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    glb_url TEXT,
    dxf_url TEXT,
    status TEXT DEFAULT 'en_attente_validation',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES models(id) ON DELETE SET NULL
);

-- Table des sessions
CREATE TABLE IF NOT EXISTS sessions (
    id SERIAL PRIMARY KEY,
    session_id TEXT NOT NULL UNIQUE,
    user_id INTEGER,
    data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES admins(id)
);

-- Table des clients (pour gestion interne/devis)
CREATE TABLE IF NOT EXISTS clients (
    id SERIAL PRIMARY KEY,
    nom TEXT NOT NULL,
    prenom TEXT,
    email TEXT,
    telephone TEXT,
    adresse TEXT,
    ville TEXT,
    code_postal TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des customers (pour authentification frontend)
CREATE TABLE IF NOT EXISTS customers (
    id SERIAL PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    phone TEXT,
    address TEXT,
    city TEXT,
    postal_code TEXT,
    country TEXT DEFAULT 'France',
    stripe_customer_id TEXT,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des projets
CREATE TABLE IF NOT EXISTS projets (
    id SERIAL PRIMARY KEY,
    client_id INTEGER,
    titre TEXT NOT NULL,
    description TEXT,
    statut TEXT DEFAULT 'en_cours',
    montant DECIMAL(10,2) DEFAULT 0.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- Table des devis
CREATE TABLE IF NOT EXISTS devis (
    id SERIAL PRIMARY KEY,
    numero_devis TEXT NOT NULL UNIQUE,
    client_id INTEGER,
    projet_id INTEGER,
    montant_total DECIMAL(10,2) DEFAULT 0.0,
    statut TEXT DEFAULT 'brouillon',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (projet_id) REFERENCES projets(id)
);

-- Table des avis clients
CREATE TABLE IF NOT EXISTS avis (
    id SERIAL PRIMARY KEY,
    user_id TEXT,
    author_name TEXT NOT NULL,
    rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
    text TEXT NOT NULL,
    date TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table des configurations sauvegardées par les clients
CREATE TABLE IF NOT EXISTS saved_configurations (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    model_id INTEGER,
    name TEXT NOT NULL,
    prompt TEXT NOT NULL,
    config_data TEXT,
    glb_url TEXT,
    price DECIMAL(10,2) NOT NULL,
    thumbnail_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE SET NULL
);

-- Table du panier
CREATE TABLE IF NOT EXISTS cart_items (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    configuration_id INTEGER NOT NULL,
    quantity INTEGER DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (configuration_id) REFERENCES saved_configurations(id) ON DELETE CASCADE,
    UNIQUE(customer_id, configuration_id)
);

-- Table des commandes
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    order_number TEXT NOT NULL UNIQUE,
    status TEXT DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    shipping_address TEXT,
    billing_address TEXT,
    payment_method TEXT,
    payment_status TEXT DEFAULT 'pending',
    stripe_payment_intent_id TEXT,
    notes TEXT,
    admin_notes TEXT,
    name TEXT,
    confirmation_email_sent BOOLEAN DEFAULT FALSE,
    deposit_amount DECIMAL(10,2),
    remaining_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP,
    shipped_at TIMESTAMP,
    delivered_at TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
);

-- Table des items de commande
CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    configuration_id INTEGER NOT NULL,
    prompt TEXT NOT NULL,
    config_data TEXT,
    glb_url TEXT,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    production_status TEXT DEFAULT 'pending',
    production_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (configuration_id) REFERENCES saved_configurations(id) ON DELETE RESTRICT
);

-- Table des notifications utilisateur
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    related_order_id INTEGER,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (related_order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Table des notifications admin
CREATE TABLE IF NOT EXISTS admin_notifications (
    id SERIAL PRIMARY KEY,
    admin_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    message TEXT NOT NULL,
    related_id INTEGER,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- Table des échantillons de matériaux
CREATE TABLE IF NOT EXISTS sample_types (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    material TEXT NOT NULL,
    description TEXT,
    active BOOLEAN DEFAULT TRUE,
    position INTEGER DEFAULT 0,
    price_per_m2 DECIMAL(10,2) DEFAULT 0,
    unit_price DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sample_colors (
    id SERIAL PRIMARY KEY,
    type_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    hex TEXT,
    image_url TEXT,
    active BOOLEAN DEFAULT TRUE,
    position INTEGER DEFAULT 0,
    price_per_m2 DECIMAL(10,2) DEFAULT 0,
    unit_price DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES sample_types(id) ON DELETE CASCADE
);

-- Table des templates
CREATE TABLE IF NOT EXISTS templates (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    prompt TEXT NOT NULL,
    config_data TEXT,
    image_url TEXT,
    category TEXT,
    price DECIMAL(10,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table email_templates
CREATE TABLE IF NOT EXISTS email_templates (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    subject TEXT NOT NULL,
    body TEXT NOT NULL,
    variables TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table rate_limits
CREATE TABLE IF NOT EXISTS rate_limits (
    id SERIAL PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'ip',
    attempts INTEGER DEFAULT 0,
    lockout_count INTEGER DEFAULT 0,
    first_attempt_at TIMESTAMP,
    locked_until TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(identifier, type)
);

-- =============================================================================
-- INDEX
-- =============================================================================
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_configurations_user_id ON configurations(user_id);
CREATE INDEX IF NOT EXISTS idx_configurations_user_session ON configurations(user_session);
CREATE INDEX IF NOT EXISTS idx_configurations_template_id ON configurations(template_id);
CREATE INDEX IF NOT EXISTS idx_saved_configurations_customer ON saved_configurations(customer_id);
CREATE INDEX IF NOT EXISTS idx_cart_items_customer ON cart_items(customer_id);
CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_number ON orders(order_number);
CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_notifications_customer ON notifications(customer_id, is_read);
CREATE INDEX IF NOT EXISTS idx_sample_types_material ON sample_types(material);
CREATE INDEX IF NOT EXISTS idx_sample_types_active ON sample_types(active);
CREATE INDEX IF NOT EXISTS idx_sample_colors_type_id ON sample_colors(type_id);
CREATE INDEX IF NOT EXISTS idx_sample_colors_active ON sample_colors(active);

-- =============================================================================
-- DONNEES PAR DEFAUT
-- =============================================================================

-- Insérer les 3 meubles TV de base
INSERT INTO models (id, name, description, prompt, price, image_url) VALUES
(1, 'Meuble TV Scandinave', 'Meuble TV au design scandinave épuré avec 3 compartiments', 'M1(1700,500,730)EFbV3(,T,)', 899.00, '/images/meuble-scandinave.jpg'),
(2, 'Meuble TV Moderne', 'Meuble TV moderne avec 2 tiroirs et finition laquée', 'M1(2000,400,600)EFbV2(T,T)', 1099.00, '/images/meuble-moderne.jpg'),
(3, 'Meuble TV Compact', 'Meuble TV compact idéal pour petits espaces', 'M1(1200,350,650)EFbV4(,,T,)', 699.00, '/images/meuble-compact.jpg')
ON CONFLICT (id) DO NOTHING;

-- Réinitialiser la séquence models
SELECT setval('models_id_seq', (SELECT COALESCE(MAX(id), 0) FROM models));

-- Admin par défaut (mot de passe: admin123)
INSERT INTO admins (username, password, email)
VALUES ('admin', '$2y$12$3uQQHtqzlQH5eptxZJkJoudv4TsExrfwl7T22u4gxIlzpJSxBbmtO', 'admin@archimeuble.com')
ON CONFLICT (username) DO NOTHING;

-- Échantillons: sample_types (Aggloméré)
INSERT INTO sample_types (id, name, material, description, active, position) VALUES
(7, 'Ambre solaire', 'Aggloméré', 'La lumière du soleil fait ressortir la clareté de votre matériau', TRUE, 0),
(9, 'Blanc opalin', 'Aggloméré', NULL, TRUE, 1),
(8, 'Chêne naturel', 'Aggloméré', NULL, TRUE, 2),
(10, 'Gris galet', 'Aggloméré', NULL, TRUE, 3),
(11, 'Kaki organique', 'Aggloméré', NULL, TRUE, 4),
(13, 'Noir profond', 'Aggloméré', NULL, TRUE, 5),
(12, 'Terracotta solaire', 'Aggloméré', NULL, TRUE, 6)
ON CONFLICT (id) DO NOTHING;

INSERT INTO sample_colors (type_id, name, hex, active, position) VALUES
(7, 'Ambre solaire', '#D6A546', TRUE, 0),
(9, 'Blanc opalin', '#f5f2ec', TRUE, 0),
(8, 'Chene naturel', '#d6c5b2', TRUE, 0),
(10, 'Gris galet', '#b7b2ac', TRUE, 0),
(11, 'Kaki organique', '#8b8a5c', TRUE, 0),
(13, 'Noir profond', '#1f1d1b', TRUE, 0),
(12, 'Terracotta solaire', '#d37a4a', TRUE, 0)
ON CONFLICT DO NOTHING;

-- Échantillons: sample_types (MDF + revêtement)
INSERT INTO sample_types (id, name, material, description, active, position) VALUES
(14, 'Bleu minéral', 'MDF + revêtement (mélaminé)', NULL, TRUE, 0),
(16, 'Bleu nuit velours', 'MDF + revêtement (mélaminé)', NULL, TRUE, 1),
(15, 'Brume azur', 'MDF + revêtement (mélaminé)', NULL, TRUE, 2),
(20, 'Pourpre impérial', 'MDF + revêtement (mélaminé)', NULL, TRUE, 3),
(19, 'Prune velours', 'MDF + revêtement (mélaminé)', NULL, TRUE, 4),
(17, 'Turquoise lagon', 'MDF + revêtement (mélaminé)', NULL, TRUE, 5),
(18, 'Violet brumeux', 'MDF + revêtement (mélaminé)', NULL, TRUE, 6)
ON CONFLICT (id) DO NOTHING;

INSERT INTO sample_colors (type_id, name, hex, active, position) VALUES
(14, 'Bleu mineral', '#6c8ca6', TRUE, 0),
(16, 'Bleu nuit velours', '#2d3e58', TRUE, 0),
(15, 'Brume azur', '#a7c4cf', TRUE, 0),
(20, 'Pourpre imperial', '#6f3c62', TRUE, 0),
(19, 'Prune velours', '#4d3057', TRUE, 0),
(17, 'Turquoise lagon', '#3a9d9f', TRUE, 0),
(18, 'Violet brumeux', '#7b6a93', TRUE, 0)
ON CONFLICT DO NOTHING;

-- Échantillons: sample_types (Plaqué bois)
INSERT INTO sample_types (id, name, material, description, active, position) VALUES
(27, 'Blush pêche', 'Plaqué bois', NULL, TRUE, 0),
(21, 'Noisette caramélisée', 'Plaqué bois', NULL, TRUE, 1),
(22, 'Noyer fumé', 'Plaqué bois', NULL, TRUE, 2),
(23, 'Rouge grenat', 'Plaqué bois', NULL, TRUE, 3),
(24, 'Saule poudré', 'Plaqué bois', NULL, TRUE, 4),
(25, 'Vert forêt profonde', 'Plaqué bois', NULL, TRUE, 5),
(26, 'Vert sauge', 'Plaqué bois', NULL, TRUE, 6)
ON CONFLICT (id) DO NOTHING;

INSERT INTO sample_colors (type_id, name, hex, active, position) VALUES
(27, 'Blush peche', '#f2b9a8', TRUE, 0),
(21, 'Noisette caramelisee', '#b17a55', TRUE, 0),
(22, 'Noyer fume', '#7b4b30', TRUE, 0),
(23, 'Rouge grenat', '#a5393b', TRUE, 0),
(24, 'Saule poudre', '#7c9885', TRUE, 0),
(25, 'Vert foret profonde', '#2f4a3e', TRUE, 0),
(26, 'Vert sauge', '#809d7a', TRUE, 0)
ON CONFLICT DO NOTHING;

-- Réinitialiser les séquences après insertion avec IDs explicites
SELECT setval('sample_types_id_seq', (SELECT COALESCE(MAX(id), 0) FROM sample_types));
SELECT setval('sample_colors_id_seq', (SELECT COALESCE(MAX(id), 0) FROM sample_colors));
SELECT setval('admins_id_seq', (SELECT COALESCE(MAX(id), 0) FROM admins));
