-- Migration pour les façades
-- Créé le 2026-01-11

-- Table principale des façades
CREATE TABLE IF NOT EXISTS facades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    width DECIMAL(10, 2) NOT NULL,
    height DECIMAL(10, 2) NOT NULL,
    depth DECIMAL(10, 2) NOT NULL,
    base_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    image_url VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des matériaux/couleurs disponibles pour les façades
CREATE TABLE IF NOT EXISTS facade_materials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    color_hex VARCHAR(7) NOT NULL,
    texture_url VARCHAR(500),
    price_modifier DECIMAL(10, 2) DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des types de perçages disponibles
CREATE TABLE IF NOT EXISTS facade_drilling_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon_svg TEXT,
    price DECIMAL(10, 2) DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des configurations de façades sauvegardées
CREATE TABLE IF NOT EXISTS saved_facades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER,
    facade_id INTEGER,
    configuration_data TEXT NOT NULL, -- JSON avec width, height, depth, material, drillings, etc.
    preview_image TEXT,
    total_price DECIMAL(10, 2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (facade_id) REFERENCES facades(id) ON DELETE CASCADE
);

-- Données initiales pour les matériaux
INSERT INTO facade_materials (name, color_hex, texture_url, price_modifier, is_active) VALUES
    ('Chêne Naturel', '#D8C7A1', '/textures/chene-naturel.jpg', 0, 1),
    ('Chêne Foncé', '#8B7355', '/textures/chene-fonce.jpg', 10, 1),
    ('Blanc Mat', '#FFFFFF', NULL, -5, 1),
    ('Noir Mat', '#1A1917', NULL, 5, 1),
    ('Gris Anthracite', '#4A4A4A', NULL, 0, 1),
    ('Bleu Pastel', '#A8C5DD', NULL, 15, 1),
    ('Vert Sauge', '#9CAF88', NULL, 15, 1);

-- Données initiales pour les types de perçages
INSERT INTO facade_drilling_types (name, description, icon_svg, price, is_active) VALUES
    ('Poignée Ronde', 'Perçage circulaire pour poignée ronde', '<circle cx="12" cy="12" r="5"/>', 5, 1),
    ('Poignée Longue', 'Perçage horizontal pour poignée longue', '<rect x="5" y="10" width="14" height="4" rx="2"/>', 7, 1),
    ('Serrure', 'Perçage pour serrure avec cylindre', '<circle cx="12" cy="12" r="3"/><rect x="10" y="15" width="4" height="5"/>', 15, 1),
    ('Passage de Câble', 'Perçage circulaire pour passage de câble', '<circle cx="12" cy="12" r="4" stroke-dasharray="2,2"/>', 3, 1),
    ('Aération', 'Grille d''aération', '<rect x="6" y="8" width="12" height="8" fill="none" stroke="currentColor"/><line x1="6" y1="10" x2="18" y2="10"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="6" y1="14" x2="18" y2="14"/>', 8, 1);

-- Index pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_facades_active ON facades(is_active);
CREATE INDEX IF NOT EXISTS idx_facade_materials_active ON facade_materials(is_active);
CREATE INDEX IF NOT EXISTS idx_facade_drilling_types_active ON facade_drilling_types(is_active);
CREATE INDEX IF NOT EXISTS idx_saved_facades_customer ON saved_facades(customer_id);
CREATE INDEX IF NOT EXISTS idx_saved_facades_facade ON saved_facades(facade_id);
