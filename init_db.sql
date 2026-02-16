-- =============================================================================
-- ArchiMeuble - Initialisation PostgreSQL COMPLETE (47 tables)
-- Ce fichier cree le schema de base de donnees complet
-- Les definitions correspondent EXACTEMENT a celles de Database.php
-- Ordre: tables sans FK d'abord, puis tables avec FK dans l'ordre des dependances
-- =============================================================================

-- Supprimer les cles etrangeres invalides si elles existent
DO $$ BEGIN
    ALTER TABLE configurations DROP CONSTRAINT IF EXISTS configurations_user_id_fkey;
    ALTER TABLE configurations DROP CONSTRAINT IF EXISTS configurations_template_id_fkey;
    ALTER TABLE avis DROP CONSTRAINT IF EXISTS avis_user_id_fkey;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- =============================================================================
-- NIVEAU 0 : Tables sans aucune dependance FK
-- =============================================================================

CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    name TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    email TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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

CREATE TABLE IF NOT EXISTS avis (
    id SERIAL PRIMARY KEY,
    user_id TEXT,
    author_name TEXT NOT NULL,
    rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
    text TEXT NOT NULL,
    date TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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

CREATE TABLE IF NOT EXISTS email_templates (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    subject TEXT NOT NULL,
    body TEXT NOT NULL,
    variables TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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

CREATE TABLE IF NOT EXISTS pricing (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    price_per_m3 DECIMAL(10,2) NOT NULL DEFAULT 1000.0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS pricing_config (
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
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS catalogue_items (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    material VARCHAR(100),
    dimensions VARCHAR(100),
    unit_price DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) DEFAULT 'piece',
    stock_quantity INTEGER DEFAULT 0,
    min_order_quantity INTEGER DEFAULT 1,
    is_available BOOLEAN DEFAULT TRUE,
    image_url VARCHAR(500),
    weight DECIMAL(8,2),
    tags TEXT,
    variation_label VARCHAR(100) DEFAULT 'Couleur / Finition',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS facades (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    width DECIMAL(10,2) NOT NULL,
    height DECIMAL(10,2) NOT NULL,
    depth DECIMAL(10,2) NOT NULL,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS facade_materials (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    color_hex VARCHAR(7) NOT NULL,
    texture_url VARCHAR(500),
    price_modifier DECIMAL(10,2) DEFAULT 0,
    price_per_m2 DECIMAL(10,2) DEFAULT 150,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS facade_drilling_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon_svg TEXT,
    price DECIMAL(10,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS facade_settings (
    id SERIAL PRIMARY KEY,
    setting_key TEXT UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS calendly_appointments (
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
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS password_resets (
    id SERIAL PRIMARY KEY,
    email TEXT NOT NULL,
    token TEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    slug TEXT NOT NULL UNIQUE,
    description TEXT,
    image_url TEXT,
    display_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS realisations (
    id SERIAL PRIMARY KEY,
    titre TEXT NOT NULL,
    description TEXT,
    image_url TEXT,
    date_projet TEXT,
    categorie TEXT,
    lieu TEXT,
    dimensions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS quote_requests (
    id SERIAL PRIMARY KEY,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    description TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- NIVEAU 1 : Tables avec FK vers les tables de niveau 0
-- =============================================================================

CREATE TABLE IF NOT EXISTS sessions (
    id SERIAL PRIMARY KEY,
    session_id TEXT NOT NULL UNIQUE,
    user_id INTEGER,
    data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES admins(id)
);

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
    deposit_stripe_intent_id TEXT,
    balance_stripe_intent_id TEXT,
    deposit_payment_status TEXT DEFAULT 'pending',
    balance_payment_status TEXT DEFAULT 'pending',
    notes TEXT,
    admin_notes TEXT,
    name TEXT,
    confirmation_email_sent BOOLEAN DEFAULT FALSE,
    payment_strategy TEXT DEFAULT 'full',
    deposit_percentage DECIMAL(5,2) DEFAULT 0,
    deposit_amount DECIMAL(10,2),
    remaining_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP,
    shipped_at TIMESTAMP,
    delivered_at TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS email_verifications (
    id SERIAL PRIMARY KEY,
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used BOOLEAN DEFAULT FALSE
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

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS catalogue_item_variations (
    id SERIAL PRIMARY KEY,
    catalogue_item_id INTEGER NOT NULL,
    color_name VARCHAR(100) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (catalogue_item_id) REFERENCES catalogue_items(id) ON DELETE CASCADE,
    UNIQUE(catalogue_item_id, color_name)
);

CREATE TABLE IF NOT EXISTS saved_facades (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER,
    facade_id INTEGER,
    configuration_data TEXT NOT NULL,
    preview_image TEXT,
    total_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (facade_id) REFERENCES facades(id) ON DELETE CASCADE
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS realisation_images (
    id SERIAL PRIMARY KEY,
    realisation_id INTEGER NOT NULL,
    image_url TEXT NOT NULL,
    ordre INTEGER DEFAULT 0,
    legende TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (realisation_id) REFERENCES realisations(id) ON DELETE CASCADE
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS quote_request_files (
    id SERIAL PRIMARY KEY,
    quote_request_id INTEGER NOT NULL,
    file_name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_type TEXT NOT NULL,
    file_size INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_request_id) REFERENCES quote_requests(id) ON DELETE CASCADE
);

-- =============================================================================
-- NIVEAU 2 : Tables avec FK vers les tables de niveau 1
-- =============================================================================

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

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS cart_catalogue_items (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    catalogue_item_id INTEGER NOT NULL,
    variation_id INTEGER,
    quantity INTEGER DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (catalogue_item_id) REFERENCES catalogue_items(id) ON DELETE CASCADE
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS cart_sample_items (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    sample_color_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (sample_color_id) REFERENCES sample_colors(id) ON DELETE CASCADE,
    UNIQUE(customer_id, sample_color_id)
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS facade_cart_items (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    config_data TEXT NOT NULL,
    quantity INTEGER DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

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

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS order_catalogue_items (
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
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS order_sample_items (
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
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS order_facade_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    config_data TEXT NOT NULL,
    quantity INTEGER DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

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

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS payment_links (
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
);

-- Definition identique a Database.php
CREATE TABLE IF NOT EXISTS payment_installments (
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
);

CREATE TABLE IF NOT EXISTS stripe_payment_intents (
    id SERIAL PRIMARY KEY,
    payment_intent_id TEXT NOT NULL UNIQUE,
    order_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    amount INTEGER NOT NULL,
    currency TEXT DEFAULT 'eur',
    status TEXT NOT NULL,
    metadata TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
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
CREATE INDEX IF NOT EXISTS idx_pricing_active ON pricing(is_active);
CREATE INDEX IF NOT EXISTS idx_pricing_name ON pricing(name);
CREATE INDEX IF NOT EXISTS idx_payment_links_token ON payment_links(token);
CREATE INDEX IF NOT EXISTS idx_payment_links_order ON payment_links(order_id);
CREATE INDEX IF NOT EXISTS idx_payment_links_status ON payment_links(status);
CREATE INDEX IF NOT EXISTS idx_payment_links_expires ON payment_links(expires_at);
CREATE INDEX IF NOT EXISTS idx_stripe_pi_payment_intent ON stripe_payment_intents(payment_intent_id);
CREATE INDEX IF NOT EXISTS idx_stripe_pi_order ON stripe_payment_intents(order_id);
CREATE INDEX IF NOT EXISTS idx_customers_stripe ON customers(stripe_customer_id);
CREATE INDEX IF NOT EXISTS idx_realisation_images_realisation_id ON realisation_images(realisation_id);
CREATE INDEX IF NOT EXISTS idx_installments_order ON payment_installments(order_id);
CREATE INDEX IF NOT EXISTS idx_installments_customer ON payment_installments(customer_id);
CREATE INDEX IF NOT EXISTS idx_installments_due_date ON payment_installments(due_date);
CREATE INDEX IF NOT EXISTS idx_installments_status ON payment_installments(status);
CREATE INDEX IF NOT EXISTS idx_email_verifications_email ON email_verifications(email);
CREATE INDEX IF NOT EXISTS idx_email_verifications_expires ON email_verifications(expires_at);
CREATE INDEX IF NOT EXISTS idx_cart_sample_items_customer ON cart_sample_items(customer_id);
CREATE INDEX IF NOT EXISTS idx_cart_sample_items_color ON cart_sample_items(sample_color_id);
CREATE INDEX IF NOT EXISTS idx_order_sample_items_order ON order_sample_items(order_id);
CREATE INDEX IF NOT EXISTS idx_quote_requests_status ON quote_requests(status);
CREATE INDEX IF NOT EXISTS idx_quote_requests_created ON quote_requests(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_pricing_config_category ON pricing_config(category);
CREATE INDEX IF NOT EXISTS idx_pricing_config_active ON pricing_config(is_active);
CREATE INDEX IF NOT EXISTS idx_pricing_config_lookup ON pricing_config(category, item_type, param_name);

-- =============================================================================
-- DONNEES PAR DEFAUT - PRICING CONFIG
-- =============================================================================

-- Materiaux
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('materials', 'agglomere', 'supplement', 0, 'eur', 'Supplement pour agglomere'),
('materials', 'agglomere', 'price_per_m2', 50, 'eur_m2', 'Prix au m2 de l''agglomere'),
('materials', 'mdf_melamine', 'supplement', 70, 'eur', 'Supplement pour MDF melamine'),
('materials', 'mdf_melamine', 'price_per_m2', 80, 'eur_m2', 'Prix au m2 du MDF melamine'),
('materials', 'plaque_bois', 'supplement', 140, 'eur', 'Supplement pour plaque bois'),
('materials', 'plaque_bois', 'price_per_m2', 150, 'eur_m2', 'Prix au m2 du plaque bois')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Tiroirs
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('drawers', 'standard', 'base_price', 35, 'eur', 'Prix de base d''un tiroir standard'),
('drawers', 'standard', 'coefficient', 0.0001, 'coefficient', 'Coefficient (x largeur x profondeur en mm2)'),
('drawers', 'push', 'base_price', 45, 'eur', 'Prix de base d''un tiroir push'),
('drawers', 'push', 'coefficient', 0.0001, 'coefficient', 'Coefficient (x largeur x profondeur en mm2)')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Etageres
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('shelves', 'glass', 'price_per_m2', 250, 'eur_m2', 'Prix du verre au m2'),
('shelves', 'standard', 'price_per_m2', 100, 'eur_m2', 'Prix au m2 d''une etagere standard')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Eclairage LED
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('lighting', 'led', 'price_per_linear_meter', 15, 'eur_linear_m', 'Prix de la LED par metre lineaire')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Passe-cable
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('cables', 'pass_cable', 'fixed_price', 10, 'eur', 'Prix fixe pour un passe-cable')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Socles
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('bases', 'none', 'fixed_price', 0, 'eur', 'Pas de socle'),
('bases', 'wood', 'coefficient', 0.0001, 'coefficient', 'Coefficient (x largeur x profondeur en mm2)'),
('bases', 'wood', 'price_per_m3', 800, 'eur_m3', 'Prix du bois pour socle au m3'),
('bases', 'wood', 'height', 80, 'mm', 'Hauteur fixe du socle bois'),
('bases', 'metal', 'price_per_foot', 20, 'eur', 'Prix d''un pied metallique'),
('bases', 'metal', 'foot_interval', 2000, 'mm', 'Intervalle : 2 pieds tous les 2000mm (2m)'),
('bases', 'metal', 'base_foot_count', 2, 'units', 'Nombre minimum de pieds (base)')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Charnieres
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('hinges', 'standard', 'price_per_unit', 5, 'eur', 'Prix d''une charniere standard')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Portes
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('doors', 'simple', 'coefficient', 0.00004, 'coefficient', 'Coefficient porte simple (x longueur x hauteur en mm2)'),
('doors', 'simple', 'hinge_count', 2, 'units', 'Nombre de charnieres pour une porte simple'),
('doors', 'double', 'coefficient', 0.00008, 'coefficient', 'Coefficient double porte (x longueur x hauteur en mm2)'),
('doors', 'double', 'hinge_count', 4, 'units', 'Nombre de charnieres pour une double porte'),
('doors', 'glass', 'coefficient', 0.00009, 'coefficient', 'Coefficient porte vitree (x longueur x hauteur en mm2)'),
('doors', 'glass', 'hinge_count', 2, 'units', 'Nombre de charnieres pour une porte vitree'),
('doors', 'push', 'coefficient', 0.00005, 'coefficient', 'Coefficient porte push (x longueur x hauteur en mm2)'),
('doors', 'push', 'hinge_count', 2, 'units', 'Nombre de charnieres pour une porte push')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Colonnes
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('columns', 'standard', 'price_per_m2', 120, 'eur_m2', 'Prix au m2 d''une colonne')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Caisson
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('casing', 'full', 'coefficient', 1.2, 'coefficient', 'Coefficient pour le caisson complet (majoration complexite)')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Penderie
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('wardrobe', 'rod', 'price_per_linear_meter', 20, 'eur_linear_m', 'Prix de la barre de penderie par metre lineaire')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Poignees
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('handles', 'horizontal_bar', 'price_per_unit', 15, 'eur', 'Prix d''une poignee barre horizontale'),
('handles', 'vertical_bar', 'price_per_unit', 15, 'eur', 'Prix d''une poignee barre verticale'),
('handles', 'knob', 'price_per_unit', 10, 'eur', 'Prix d''un bouton de porte'),
('handles', 'recessed', 'price_per_unit', 20, 'eur', 'Prix d''une poignee encastree')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- Affichage prix
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('display', 'price', 'display_mode', 0, 'units', 'Mode d''affichage (0=Direct, 1=Intervalle)'),
('display', 'price', 'deviation_range', 100, 'eur', 'Ecart pour l''affichage en intervalle')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

-- =============================================================================
-- DONNEES PAR DEFAUT
-- =============================================================================

INSERT INTO models (id, name, description, prompt, price, image_url) VALUES
(1, 'Meuble TV Scandinave', 'Meuble TV au design scandinave epure avec 3 compartiments', 'M1(1700,500,730)EFbV3(,T,)', 899.00, '/images/meuble-scandinave.jpg'),
(2, 'Meuble TV Moderne', 'Meuble TV moderne avec 2 tiroirs et finition laquee', 'M1(2000,400,600)EFbV2(T,T)', 1099.00, '/images/meuble-moderne.jpg'),
(3, 'Meuble TV Compact', 'Meuble TV compact ideal pour petits espaces', 'M1(1200,350,650)EFbV4(,,T,)', 699.00, '/images/meuble-compact.jpg')
ON CONFLICT (id) DO NOTHING;
SELECT setval('models_id_seq', (SELECT COALESCE(MAX(id), 0) FROM models));

INSERT INTO admins (username, password, email)
VALUES ('admin', '$2y$12$3uQQHtqzlQH5eptxZJkJoudv4TsExrfwl7T22u4gxIlzpJSxBbmtO', 'admin@archimeuble.com')
ON CONFLICT (username) DO NOTHING;

INSERT INTO pricing (name, description, price_per_m3, is_active) VALUES
('default', 'Prix par defaut', 1500.0, TRUE),
('premium', 'Prix premium', 2500.0, TRUE),
('budget', 'Prix economique', 1000.0, TRUE)
ON CONFLICT (name) DO NOTHING;

INSERT INTO sample_types (id, name, material, description, active, position) VALUES
(7, 'Ambre solaire', 'Agglomere', 'La lumiere du soleil fait ressortir la clarete de votre materiau', TRUE, 0),
(9, 'Blanc opalin', 'Agglomere', NULL, TRUE, 1),
(8, 'Chene naturel', 'Agglomere', NULL, TRUE, 2),
(10, 'Gris galet', 'Agglomere', NULL, TRUE, 3),
(11, 'Kaki organique', 'Agglomere', NULL, TRUE, 4),
(13, 'Noir profond', 'Agglomere', NULL, TRUE, 5),
(12, 'Terracotta solaire', 'Agglomere', NULL, TRUE, 6),
(14, 'Bleu mineral', 'MDF + revetement (melamine)', NULL, TRUE, 0),
(16, 'Bleu nuit velours', 'MDF + revetement (melamine)', NULL, TRUE, 1),
(15, 'Brume azur', 'MDF + revetement (melamine)', NULL, TRUE, 2),
(20, 'Pourpre imperial', 'MDF + revetement (melamine)', NULL, TRUE, 3),
(19, 'Prune velours', 'MDF + revetement (melamine)', NULL, TRUE, 4),
(17, 'Turquoise lagon', 'MDF + revetement (melamine)', NULL, TRUE, 5),
(18, 'Violet brumeux', 'MDF + revetement (melamine)', NULL, TRUE, 6),
(27, 'Blush peche', 'Plaque bois', NULL, TRUE, 0),
(21, 'Noisette caramelisee', 'Plaque bois', NULL, TRUE, 1),
(22, 'Noyer fume', 'Plaque bois', NULL, TRUE, 2),
(23, 'Rouge grenat', 'Plaque bois', NULL, TRUE, 3),
(24, 'Saule poudre', 'Plaque bois', NULL, TRUE, 4),
(25, 'Vert foret profonde', 'Plaque bois', NULL, TRUE, 5),
(26, 'Vert sauge', 'Plaque bois', NULL, TRUE, 6)
ON CONFLICT (id) DO NOTHING;

INSERT INTO sample_colors (type_id, name, hex, active, position) VALUES
(7, 'Ambre solaire', '#D6A546', TRUE, 0),
(9, 'Blanc opalin', '#f5f2ec', TRUE, 0),
(8, 'Chene naturel', '#d6c5b2', TRUE, 0),
(10, 'Gris galet', '#b7b2ac', TRUE, 0),
(11, 'Kaki organique', '#8b8a5c', TRUE, 0),
(13, 'Noir profond', '#1f1d1b', TRUE, 0),
(12, 'Terracotta solaire', '#d37a4a', TRUE, 0),
(14, 'Bleu mineral', '#6c8ca6', TRUE, 0),
(16, 'Bleu nuit velours', '#2d3e58', TRUE, 0),
(15, 'Brume azur', '#a7c4cf', TRUE, 0),
(20, 'Pourpre imperial', '#6f3c62', TRUE, 0),
(19, 'Prune velours', '#4d3057', TRUE, 0),
(17, 'Turquoise lagon', '#3a9d9f', TRUE, 0),
(18, 'Violet brumeux', '#7b6a93', TRUE, 0),
(27, 'Blush peche', '#f2b9a8', TRUE, 0),
(21, 'Noisette caramelisee', '#b17a55', TRUE, 0),
(22, 'Noyer fume', '#7b4b30', TRUE, 0),
(23, 'Rouge grenat', '#a5393b', TRUE, 0),
(24, 'Saule poudre', '#7c9885', TRUE, 0),
(25, 'Vert foret profonde', '#2f4a3e', TRUE, 0),
(26, 'Vert sauge', '#809d7a', TRUE, 0)
ON CONFLICT DO NOTHING;

SELECT setval('sample_types_id_seq', (SELECT COALESCE(MAX(id), 0) FROM sample_types));
SELECT setval('sample_colors_id_seq', (SELECT COALESCE(MAX(id), 0) FROM sample_colors));
SELECT setval('admins_id_seq', (SELECT COALESCE(MAX(id), 0) FROM admins));
