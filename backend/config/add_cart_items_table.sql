-- Migration: Ajouter la table cart_items pour persister le panier
-- Date: 2025-10-29

CREATE TABLE IF NOT EXISTS cart_items (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    configuration_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (configuration_id) REFERENCES configurations(id) ON DELETE CASCADE,
    UNIQUE(customer_id, configuration_id)
);

CREATE INDEX idx_cart_customer ON cart_items(customer_id);
CREATE INDEX idx_cart_configuration ON cart_items(configuration_id);
