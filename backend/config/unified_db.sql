-- ArchiMeuble - Schéma de base de données unifié
-- Date : 2025-10-21
-- Description : Schéma unique pour le frontend Next.js et backend PHP

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    name TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des administrateurs
CREATE TABLE IF NOT EXISTS admins (
    email TEXT PRIMARY KEY,
    password_hash TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des modèles/templates de meubles (fusion templates + models)
CREATE TABLE IF NOT EXISTS models (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    prompt TEXT NOT NULL,
    base_price REAL,
    image_path TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des configurations utilisateur
CREATE TABLE IF NOT EXISTS configurations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT,
    user_session TEXT,
    template_id INTEGER,
    config_string TEXT,
    prompt TEXT NOT NULL,
    price REAL NOT NULL,
    glb_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES models(id) ON DELETE SET NULL
);

-- Index pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_configurations_user_id ON configurations(user_id);
CREATE INDEX IF NOT EXISTS idx_configurations_user_session ON configurations(user_session);
CREATE INDEX IF NOT EXISTS idx_configurations_template_id ON configurations(template_id);

-- Insertion des 3 meubles TV de base
INSERT OR IGNORE INTO models (id, name, description, prompt, base_price, image_path) VALUES
(1, 'Meuble TV Scandinave', 'Meuble TV au design scandinave épuré avec 3 compartiments', 'M1(1700,500,730)EFH3(F,T,F)', 899.00, '/frontend/assets/images/meuble-scandinave.jpg'),
(2, 'Meuble TV Moderne', 'Meuble TV moderne avec 2 tiroirs et finition laquée', 'M1(2000,400,600)EFH2(T,T)', 1099.00, '/frontend/assets/images/meuble-moderne.jpg'),
(3, 'Meuble TV Compact', 'Meuble TV compact idéal pour petits espaces', 'M1(1200,350,650)EFH4(F,F,T,F)', 699.00, '/frontend/assets/images/meuble-compact.jpg');

-- ============================================================================
-- ÉCHANTILLONS DE FAÇADES (ajouté le 2025-11-02)
-- ============================================================================

-- Table des types d'échantillons (sample_types)
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

-- Table des couleurs pour chaque type (sample_colors)
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

-- Index pour améliorer les performances des échantillons
CREATE INDEX IF NOT EXISTS idx_sample_types_material ON sample_types(material);
CREATE INDEX IF NOT EXISTS idx_sample_types_active ON sample_types(active);
CREATE INDEX IF NOT EXISTS idx_sample_colors_type_id ON sample_colors(type_id);
CREATE INDEX IF NOT EXISTS idx_sample_colors_active ON sample_colors(active);

-- Données des échantillons clients
-- Note: Ces données correspondent aux choix du client

-- ============================================
-- AGGLOMÉRÉ
-- ============================================
INSERT OR IGNORE INTO sample_types (id, name, material, description, active, position) VALUES
(7, 'Ambre solaire', 'Aggloméré', 'La lumière du soleil fait ressortir la clareté de votre matériau', 1, 0),
(9, 'Blanc opalin', 'Aggloméré', NULL, 1, 1),
(8, 'Chêne naturel', 'Aggloméré', NULL, 1, 2),
(10, 'Gris galet', 'Aggloméré', NULL, 1, 3),
(11, 'Kaki organique', 'Aggloméré', NULL, 1, 4),
(13, 'Noir profond', 'Aggloméré', NULL, 1, 5),
(12, 'Terracotta solaire', 'Aggloméré', NULL, 1, 6),
(29, 'Test', 'Aggloméré', 'matériau de tests', 1, 7);

INSERT OR IGNORE INTO sample_colors (type_id, name, hex, active, position) VALUES
(7, 'Ambre solaire', '#D6A546', 1, 0),
(9, 'Blanc opalin', '#f5f2ec', 1, 0),
(8, 'Chêne naturel', '#d6c5b2', 1, 0),
(10, 'Gris galet', '#b7b2ac', 1, 0),
(11, 'Kaki organique', '#8b8a5c', 1, 0),
(13, 'Noir profond', '#1f1d1b', 1, 0),
(12, 'Terracotta solaire', '#d37a4a', 1, 0),
(29, 'Test', '#e81717', 1, 0);

-- ============================================
-- MDF + REVÊTEMENT (MÉLAMINÉ)
-- ============================================
INSERT OR IGNORE INTO sample_types (id, name, material, description, active, position) VALUES
(14, 'Bleu minéral', 'MDF + revêtement (mélaminé)', NULL, 1, 0),
(16, 'Bleu nuit velours', 'MDF + revêtement (mélaminé)', NULL, 1, 1),
(15, 'Brume azur', 'MDF + revêtement (mélaminé)', NULL, 1, 2),
(20, 'Pourpre impérial', 'MDF + revêtement (mélaminé)', NULL, 1, 3),
(19, 'Prune velours', 'MDF + revêtement (mélaminé)', NULL, 1, 4),
(17, 'Turquoise lagon', 'MDF + revêtement (mélaminé)', NULL, 1, 5),
(18, 'Violet brumeux', 'MDF + revêtement (mélaminé)', NULL, 1, 6);

INSERT OR IGNORE INTO sample_colors (type_id, name, hex, active, position) VALUES
(14, 'Bleu minéral', '#6c8ca6', 1, 0),
(16, 'Bleu nuit velours', '#2d3e58', 1, 0),
(15, 'Brume azur', '#a7c4cf', 1, 0),
(20, 'Pourpre impérial', '#6f3c62', 1, 0),
(19, 'Prune velours', '#4d3057', 1, 0),
(17, 'Turquoise lagon', '#3a9d9f', 1, 0),
(18, 'Violet brumeux', '#7b6a93', 1, 0);

-- ============================================
-- PLAQUÉ BOIS
-- ============================================
INSERT OR IGNORE INTO sample_types (id, name, material, description, active, position) VALUES
(27, 'Blush pêche', 'Plaqué bois', NULL, 1, 0),
(21, 'Noisette caramélisée', 'Plaqué bois', NULL, 1, 1),
(22, 'Noyer fumé', 'Plaqué bois', NULL, 1, 2),
(23, 'Rouge grenat', 'Plaqué bois', NULL, 1, 3),
(24, 'Saule poudré', 'Plaqué bois', NULL, 1, 4),
(25, 'Vert forêt profonde', 'Plaqué bois', NULL, 1, 5),
(26, 'Vert sauge', 'Plaqué bois', NULL, 1, 6);

INSERT OR IGNORE INTO sample_colors (type_id, name, hex, active, position) VALUES
(27, 'Blush pêche', '#f2b9a8', 1, 0),
(21, 'Noisette caramélisée', '#b17a55', 1, 0),
(22, 'Noyer fumé', '#7b4b30', 1, 0),
(23, 'Rouge grenat', '#a5393b', 1, 0),
(24, 'Saule poudré', '#7c9885', 1, 0),
(25, 'Vert forêt profonde', '#2f4a3e', 1, 0),
(26, 'Vert sauge', '#809d7a', 1, 0);

-- ============================================================================
-- CALENDLY APPOINTMENTS (ajouté le 2025-11-03)
-- ============================================================================

-- Table des rendez-vous Calendly
CREATE TABLE IF NOT EXISTS calendly_appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendly_event_id TEXT UNIQUE NOT NULL,
    client_name TEXT NOT NULL,
    client_email TEXT NOT NULL,
    event_type TEXT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    timezone TEXT DEFAULT 'Europe/Paris',
    config_url TEXT,
    additional_notes TEXT,
    status TEXT DEFAULT 'scheduled',
    confirmation_sent BOOLEAN DEFAULT 0,
    reminder_24h_sent BOOLEAN DEFAULT 0,
    reminder_1h_sent BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Index pour optimiser les requêtes
CREATE INDEX IF NOT EXISTS idx_calendly_start_time ON calendly_appointments(start_time);
CREATE INDEX IF NOT EXISTS idx_calendly_status ON calendly_appointments(status);
CREATE INDEX IF NOT EXISTS idx_calendly_reminder_24h_sent ON calendly_appointments(reminder_24h_sent);
CREATE INDEX IF NOT EXISTS idx_calendly_reminder_1h_sent ON calendly_appointments(reminder_1h_sent);
CREATE INDEX IF NOT EXISTS idx_calendly_email ON calendly_appointments(client_email);
