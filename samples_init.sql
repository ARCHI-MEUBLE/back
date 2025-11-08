-- Tables des échantillons
CREATE TABLE IF NOT EXISTS sample_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    material TEXT NOT NULL,
    description TEXT,
    active INTEGER DEFAULT 1,
    position INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sample_colors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    hex TEXT,
    image_url TEXT,
    active INTEGER DEFAULT 1,
    position INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES sample_types(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sample_types_material ON sample_types(material);
CREATE INDEX IF NOT EXISTS idx_sample_types_active ON sample_types(active);
CREATE INDEX IF NOT EXISTS idx_sample_colors_type_id ON sample_colors(type_id);
CREATE INDEX IF NOT EXISTS idx_sample_colors_active ON sample_colors(active);

-- Données
INSERT OR IGNORE INTO sample_types (id, name, material, description, active, position) VALUES
(7, 'Ambre solaire', 'Agglomere', 'La lumiere du soleil fait ressortir la clarete de votre materiau', 1, 0),
(9, 'Blanc opalin', 'Agglomere', NULL, 1, 1),
(8, 'Chene naturel', 'Agglomere', NULL, 1, 2),
(10, 'Gris galet', 'Agglomere', NULL, 1, 3),
(11, 'Kaki organique', 'Agglomere', NULL, 1, 4),
(13, 'Noir profond', 'Agglomere', NULL, 1, 5),
(12, 'Terracotta solaire', 'Agglomere', NULL, 1, 6);

INSERT OR IGNORE INTO sample_colors (type_id, name, hex, active, position) VALUES
(7, 'Ambre solaire', '#D6A546', 1, 0),
(9, 'Blanc opalin', '#f5f2ec', 1, 0),
(8, 'Chene naturel', '#d6c5b2', 1, 0),
(10, 'Gris galet', '#b7b2ac', 1, 0),
(11, 'Kaki organique', '#8b8a5c', 1, 0),
(13, 'Noir profond', '#1f1d1b', 1, 0),
(12, 'Terracotta solaire', '#d37a4a', 1, 0);

INSERT OR IGNORE INTO sample_types (id, name, material, description, active, position) VALUES
(14, 'Bleu mineral', 'MDF + revetement (melamine)', NULL, 1, 0),
(16, 'Bleu nuit velours', 'MDF + revetement (melamine)', NULL, 1, 1),
(15, 'Brume azur', 'MDF + revetement (melamine)', NULL, 1, 2),
(20, 'Pourpre imperial', 'MDF + revetement (melamine)', NULL, 1, 3),
(19, 'Prune velours', 'MDF + revetement (melamine)', NULL, 1, 4),
(17, 'Turquoise lagon', 'MDF + revetement (melamine)', NULL, 1, 5),
(18, 'Violet brumeux', 'MDF + revetement (melamine)', NULL, 1, 6);

INSERT OR IGNORE INTO sample_colors (type_id, name, hex, active, position) VALUES
(14, 'Bleu mineral', '#6c8ca6', 1, 0),
(16, 'Bleu nuit velours', '#2d3e58', 1, 0),
(15, 'Brume azur', '#a7c4cf', 1, 0),
(20, 'Pourpre imperial', '#6f3c62', 1, 0),
(19, 'Prune velours', '#4d3057', 1, 0),
(17, 'Turquoise lagon', '#3a9d9f', 1, 0),
(18, 'Violet brumeux', '#7b6a93', 1, 0);

INSERT OR IGNORE INTO sample_types (id, name, material, description, active, position) VALUES
(27, 'Blush peche', 'Plaque bois', NULL, 1, 0),
(21, 'Noisette caramelisee', 'Plaque bois', NULL, 1, 1),
(22, 'Noyer fume', 'Plaque bois', NULL, 1, 2),
(23, 'Rouge grenat', 'Plaque bois', NULL, 1, 3),
(24, 'Saule poudre', 'Plaque bois', NULL, 1, 4),
(25, 'Vert foret profonde', 'Plaque bois', NULL, 1, 5),
(26, 'Vert sauge', 'Plaque bois', NULL, 1, 6);

INSERT OR IGNORE INTO sample_colors (type_id, name, hex, active, position) VALUES
(27, 'Blush peche', '#f2b9a8', 1, 0),
(21, 'Noisette caramelisee', '#b17a55', 1, 0),
(22, 'Noyer fume', '#7b4b30', 1, 0),
(23, 'Rouge grenat', '#a5393b', 1, 0),
(24, 'Saule poudre', '#7c9885', 1, 0),
(25, 'Vert foret profonde', '#2f4a3e', 1, 0),
(26, 'Vert sauge', '#809d7a', 1, 0);
