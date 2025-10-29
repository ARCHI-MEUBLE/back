-- ArchiMeuble - Tables pour système de commandes clients
-- Date : 2025-10-24
-- Description : Tables pour gérer les clients, configurations sauvegardées, panier et commandes

-- Table des clients (différente des admins)
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

-- Table des configurations sauvegardées par les clients
CREATE TABLE IF NOT EXISTS saved_configurations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    model_id INTEGER,
    name TEXT NOT NULL, -- Nom donné par le client (ex: "Mon meuble salon")
    prompt TEXT NOT NULL,
    config_data TEXT, -- JSON avec tous les paramètres (dimensions, couleur, etc.)
    glb_url TEXT,
    price REAL NOT NULL,
    thumbnail_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE SET NULL
);

-- Table du panier
CREATE TABLE IF NOT EXISTS cart_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    configuration_id INTEGER NOT NULL,
    quantity INTEGER DEFAULT 1,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (configuration_id) REFERENCES saved_configurations(id) ON DELETE CASCADE,
    UNIQUE(customer_id, configuration_id) -- Un client ne peut pas ajouter 2x la même config
);

-- Table des commandes
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    order_number TEXT NOT NULL UNIQUE, -- Ex: ORD-2025-001234
    status TEXT DEFAULT 'pending', -- pending, confirmed, in_production, shipped, delivered, cancelled
    total_amount REAL NOT NULL,
    shipping_address TEXT,
    billing_address TEXT,
    payment_method TEXT,
    payment_status TEXT DEFAULT 'pending', -- pending, paid, refunded
    notes TEXT, -- Notes du client
    admin_notes TEXT, -- Notes internes pour l'admin
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME,
    shipped_at DATETIME,
    delivered_at DATETIME,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
);

-- Table des items de commande (détails de chaque configuration commandée)
CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    configuration_id INTEGER NOT NULL,
    prompt TEXT NOT NULL, -- Copie du prompt au moment de la commande
    config_data TEXT, -- Copie de la config au moment de la commande (JSON)
    glb_url TEXT,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price REAL NOT NULL,
    total_price REAL NOT NULL,
    production_status TEXT DEFAULT 'pending', -- pending, in_progress, completed
    production_notes TEXT, -- Notes de production pour l'admin
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (configuration_id) REFERENCES saved_configurations(id) ON DELETE RESTRICT
);

-- Table des notifications pour l'admin
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL, -- 'new_order', 'customer_message', 'production_update'
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

-- Index pour performances
CREATE INDEX IF NOT EXISTS idx_customers_email ON customers(email);
CREATE INDEX IF NOT EXISTS idx_saved_configurations_customer ON saved_configurations(customer_id);
CREATE INDEX IF NOT EXISTS idx_cart_items_customer ON cart_items(customer_id);
CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_number ON orders(order_number);
CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_admin_notifications_unread ON admin_notifications(is_read, created_at);
