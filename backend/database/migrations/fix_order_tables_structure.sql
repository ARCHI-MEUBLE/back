-- Migration: Fix order tables structure
-- Date: 2026-01-25
-- Description: S'assure que les tables de commandes ont les bonnes colonnes

-- Table order_catalogue_items - Structure minimale requise
CREATE TABLE IF NOT EXISTS order_catalogue_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    catalogue_item_id INTEGER NOT NULL,
    variation_id INTEGER,
    name VARCHAR(255) NOT NULL DEFAULT 'Article',
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (catalogue_item_id) REFERENCES catalogue_items(id),
    FOREIGN KEY (variation_id) REFERENCES catalogue_item_variations(id)
);

-- Ajout de la colonne name si elle n'existe pas (pour les bases existantes)
-- Note: PostgreSQL supports ADD COLUMN IF NOT EXISTS for safe migrations
-- ALTER TABLE order_catalogue_items ADD COLUMN IF NOT EXISTS name VARCHAR(255) NOT NULL DEFAULT 'Article';

-- Table order_sample_items - Structure minimale requise
CREATE TABLE IF NOT EXISTS order_sample_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    sample_color_id INTEGER,
    sample_name VARCHAR(255),
    sample_type_name VARCHAR(255),
    material VARCHAR(255),
    image_url VARCHAR(500),
    hex VARCHAR(7),
    quantity INTEGER NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Table order_facade_items - Structure minimale requise
CREATE TABLE IF NOT EXISTS order_facade_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    config_data TEXT,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Table order_items - Structure minimale requise
CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    configuration_id INTEGER,
    prompt TEXT,
    config_data TEXT,
    glb_url VARCHAR(500),
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (configuration_id) REFERENCES configurations(id)
);
