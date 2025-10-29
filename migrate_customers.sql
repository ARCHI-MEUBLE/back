-- Migration : Transformer table clients en customers avec authentification
-- Date : 2025-10-28
-- Description : Ajoute les colonnes nécessaires pour l'authentification des clients

-- Étape 1 : Créer la nouvelle table customers avec le bon schéma
CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    phone TEXT,
    address TEXT,
    city TEXT,
    postal_code TEXT,
    country TEXT DEFAULT 'France',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Étape 2 : Créer les tables manquantes pour le système de commandes
CREATE TABLE IF NOT EXISTS saved_configurations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    model_id INTEGER,
    name TEXT NOT NULL,
    prompt TEXT NOT NULL,
    config_data TEXT,
    glb_url TEXT,
    price REAL NOT NULL,
    thumbnail_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS cart_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    configuration_id INTEGER NOT NULL,
    quantity INTEGER DEFAULT 1,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (configuration_id) REFERENCES saved_configurations(id) ON DELETE CASCADE,
    UNIQUE(customer_id, configuration_id)
);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    order_number TEXT NOT NULL UNIQUE,
    status TEXT DEFAULT 'pending',
    total_amount REAL NOT NULL,
    shipping_address TEXT,
    billing_address TEXT,
    payment_method TEXT,
    payment_status TEXT DEFAULT 'pending',
    notes TEXT,
    admin_notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME,
    shipped_at DATETIME,
    delivered_at DATETIME,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    configuration_id INTEGER NOT NULL,
    prompt TEXT NOT NULL,
    config_data TEXT,
    glb_url TEXT,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price REAL NOT NULL,
    total_price REAL NOT NULL,
    production_status TEXT DEFAULT 'pending',
    production_notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (configuration_id) REFERENCES saved_configurations(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS admin_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    related_order_id INTEGER,
    related_customer_id INTEGER,
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME,
    FOREIGN KEY (related_order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (related_customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Étape 3 : Insérer un client de test
-- Mot de passe : "client123" (hash BCrypt)
INSERT OR IGNORE INTO customers (email, password_hash, first_name, last_name, phone, address, city, postal_code, country) 
VALUES (
    'client1@archimeuble.com',
    '$2y$10$YourBcryptHashHere',
    'Client',
    'Test',
    '0601020304',
    '123 Rue de Test',
    'Paris',
    '75001',
    'France'
);

-- Note : Le hash ci-dessus est un placeholder. Il faut le générer avec PHP ou utiliser le script PHP fourni.
