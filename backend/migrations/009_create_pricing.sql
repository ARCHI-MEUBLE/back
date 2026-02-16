-- Migration: Create pricing table
-- Description: Table to store price per cubic meter for different furniture types

CREATE TABLE IF NOT EXISTS pricing (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    price_per_m3 DECIMAL(10,2) NOT NULL DEFAULT 1000.0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default pricing
INSERT INTO pricing (name, description, price_per_m3, is_active) VALUES
('default', 'Prix par défaut pour tous les meubles', 1500.0, TRUE),
('premium', 'Prix premium pour meubles haut de gamme', 2500.0, TRUE),
('budget', 'Prix économique', 1000.0, TRUE)
ON CONFLICT DO NOTHING;

CREATE INDEX IF NOT EXISTS idx_pricing_active ON pricing(is_active);
CREATE INDEX IF NOT EXISTS idx_pricing_name ON pricing(name);
