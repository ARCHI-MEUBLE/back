-- Migration: Create pricing table
-- Description: Table to store price per cubic meter for different furniture types

CREATE TABLE IF NOT EXISTS pricing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    price_per_m3 REAL NOT NULL DEFAULT 1000.0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default pricing
INSERT OR IGNORE INTO pricing (name, description, price_per_m3, is_active) VALUES
('default', 'Prix par défaut pour tous les meubles', 1500.0, 1),
('premium', 'Prix premium pour meubles haut de gamme', 2500.0, 1),
('budget', 'Prix économique', 1000.0, 1);

CREATE INDEX IF NOT EXISTS idx_pricing_active ON pricing(is_active);
CREATE INDEX IF NOT EXISTS idx_pricing_name ON pricing(name);
