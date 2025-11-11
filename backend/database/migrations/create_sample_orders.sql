-- Migration: Tables pour les commandes d'échantillons
-- Date: 2025-11-11

-- Table pour les articles d'échantillons dans le panier
CREATE TABLE IF NOT EXISTS cart_sample_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    sample_color_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (sample_color_id) REFERENCES sample_colors(id) ON DELETE CASCADE,
    UNIQUE(customer_id, sample_color_id)
);

-- Table pour les commandes d'échantillons (dans une commande)
CREATE TABLE IF NOT EXISTS order_sample_items (
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
);

-- Index pour performances
CREATE INDEX IF NOT EXISTS idx_cart_sample_items_customer ON cart_sample_items(customer_id);
CREATE INDEX IF NOT EXISTS idx_cart_sample_items_color ON cart_sample_items(sample_color_id);
CREATE INDEX IF NOT EXISTS idx_order_sample_items_order ON order_sample_items(order_id);

-- Mettre à jour la table orders pour tracker le nombre d'échantillons
-- ALTER TABLE orders ADD COLUMN sample_items_count INTEGER DEFAULT 0;
-- (Optionnel, on peut calculer dynamiquement)
