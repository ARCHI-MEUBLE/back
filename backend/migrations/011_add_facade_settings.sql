-- Migration pour ajouter les paramètres configurables des façades
-- Créé le 2026-01-12

-- Table pour stocker les paramètres configurables
CREATE TABLE IF NOT EXISTS facade_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertion des valeurs par défaut
INSERT INTO facade_settings (setting_key, setting_value, description) VALUES
('max_width_cm', '60', 'Largeur maximale en centimètres'),
('max_height_cm', '230', 'Hauteur maximale en centimètres'),
('min_width_cm', '10', 'Largeur minimale en centimètres'),
('min_height_cm', '50', 'Hauteur minimale en centimètres'),
('fixed_depth_mm', '19', 'Épaisseur fixe en millimètres');

-- Index pour la recherche rapide par clé
CREATE INDEX IF NOT EXISTS idx_facade_settings_key ON facade_settings(setting_key);
