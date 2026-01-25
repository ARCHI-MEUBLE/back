-- Migration pour les paramètres de configuration des façades
CREATE TABLE IF NOT EXISTS facade_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insérer les valeurs par défaut
INSERT OR IGNORE INTO facade_settings (setting_key, setting_value, description) VALUES 
('max_width', '600', 'Largeur maximale en mm (60 cm par défaut)'),
('max_height', '2300', 'Hauteur maximale en mm (230 cm par défaut)'),
('fixed_depth', '19', 'Épaisseur fixe en mm');
