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
