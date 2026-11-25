-- 0010 handle models: handles the configurator offers for doors and drawers, managed from the admin like the sample
-- colours. Each door or drawer can take its own model and orientation (horizontal / vertical), otherwise the one chosen
-- for the whole piece. A knob has no length. The price is per handle, whatever its orientation.
CREATE TABLE IF NOT EXISTS handle_models (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    shape TEXT NOT NULL CHECK (shape IN ('bar', 'flat_bar', 'profile', 'knob')),
    finish_hex TEXT NOT NULL CHECK (finish_hex ~ '^#[0-9A-Fa-f]{6}$'),
    length_mm INTEGER NULL CHECK (length_mm IS NULL OR length_mm BETWEEN 20 AND 800),
    image_url TEXT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (price_per_unit >= 0),
    active BOOLEAN NOT NULL DEFAULT TRUE,
    position INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Two models cannot share a name, whatever the case: the admin and the configurator tell them apart by name.
CREATE UNIQUE INDEX IF NOT EXISTS handle_models_name_key ON handle_models (LOWER(name));
CREATE INDEX IF NOT EXISTS idx_handle_models_active_position ON handle_models (active, position);

-- Three models to start with, all of them usable horizontally or vertically.
INSERT INTO handle_models (name, shape, finish_hex, length_mm, price_per_unit, position) VALUES
('Barre ronde noire', 'bar', '#1C1C1C', 160, 25, 1),
('Barre plate laiton', 'flat_bar', '#B08D57', 192, 30, 2),
('Profil aluminium', 'profile', '#C9CCCF', 200, 30, 3)
ON CONFLICT DO NOTHING;
