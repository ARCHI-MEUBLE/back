#!/bin/bash
set -e

# Utiliser les variables d'env si disponibles, sinon des valeurs par défaut adaptées au conteneur
DB_PATH="${DB_PATH:-/app/database/archimeuble.db}"
OUTPUT_DIR="${OUTPUT_DIR:-/app/models}"

echo "Initialisation de la base de données ArchiMeuble..."
echo "Chemin de la base de données: $DB_PATH"

# Créer les répertoires nécessaires dans /app (montés par docker-compose)
mkdir -p "$(dirname "$DB_PATH")"
mkdir -p "$OUTPUT_DIR"
mkdir -p /app/database
mkdir -p /app/uploads/models
mkdir -p /app/devis
mkdir -p /app/pieces
mkdir -p /app/models
mkdir -p /app/uploads

echo "Répertoires créés dans /app"

# Créer ou mettre à jour la base de données
sqlite3 "$DB_PATH" <<'EOF'
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
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    email TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des modèles/templates de meubles
CREATE TABLE IF NOT EXISTS models (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    prompt TEXT NOT NULL,
    description TEXT,
    price REAL DEFAULT 0.0,
    image_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

-- Table des sessions
CREATE TABLE IF NOT EXISTS sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT NOT NULL UNIQUE,
    user_id INTEGER,
    data TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES admins(id)
);

-- Table des clients (pour gestion interne/devis)
CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    prenom TEXT,
    email TEXT,
    telephone TEXT,
    adresse TEXT,
    ville TEXT,
    code_postal TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des customers (pour authentification frontend)
CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    phone TEXT,
    address TEXT,
    city TEXT,
    postal_code TEXT,
    country TEXT DEFAULT 'France',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des projets
CREATE TABLE IF NOT EXISTS projets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER,
    titre TEXT NOT NULL,
    description TEXT,
    statut TEXT DEFAULT 'en_cours',
    montant REAL DEFAULT 0.0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- Table des devis
CREATE TABLE IF NOT EXISTS devis (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    numero_devis TEXT NOT NULL UNIQUE,
    client_id INTEGER,
    projet_id INTEGER,
    montant_total REAL DEFAULT 0.0,
    statut TEXT DEFAULT 'brouillon',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (projet_id) REFERENCES projets(id)
);

-- Table des avis clients
CREATE TABLE IF NOT EXISTS avis (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT,
    author_name TEXT NOT NULL,
    rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
    text TEXT NOT NULL,
    date TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table des notifications admin
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    message TEXT NOT NULL,
    related_id INTEGER,
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- Table des échantillons de matériaux
-- Note: utilise sample_types et sample_colors (pas juste samples)
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

-- Index pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_configurations_user_id ON configurations(user_id);
CREATE INDEX IF NOT EXISTS idx_configurations_user_session ON configurations(user_session);
CREATE INDEX IF NOT EXISTS idx_configurations_template_id ON configurations(template_id);

-- Insérer les 3 meubles TV de base (si pas déjà présents)
INSERT OR IGNORE INTO models (id, name, description, prompt, price, image_url) VALUES
(1, 'Meuble TV Scandinave', 'Meuble TV au design scandinave épuré avec 3 compartiments', 'M1(1700,500,730)EFbV3(,T,)', 899.00, '/images/meuble-scandinave.jpg'),
(2, 'Meuble TV Moderne', 'Meuble TV moderne avec 2 tiroirs et finition laquée', 'M1(2000,400,600)EFbV2(T,T)', 1099.00, '/images/meuble-moderne.jpg'),
(3, 'Meuble TV Compact', 'Meuble TV compact idéal pour petits espaces', 'M1(1200,350,650)EFbV4(,,T,)', 699.00, '/images/meuble-compact.jpg');

EOF

# Ajouter les tables d'échantillons depuis le fichier SQL dédié
echo "Ajout des échantillons..."
sqlite3 "$DB_PATH" < /app/samples_init.sql

# Créer un admin par défaut (mot de passe: admin123)
# Hash bcrypt pour "admin123": $2y$12$3uQQHtqzlQH5eptxZJkJoudv4TsExrfwl7T22u4gxIlzpJSxBbmtO
echo "Création de l'administrateur par défaut..."
sqlite3 "$DB_PATH" <<'EOF'
INSERT OR IGNORE INTO admins (username, password, email)
VALUES ('admin', '$2y$12$3uQQHtqzlQH5eptxZJkJoudv4TsExrfwl7T22u4gxIlzpJSxBbmtO', 'admin@archimeuble.com');
EOF

echo "Base de données initialisée avec succès!"
echo "Identifiants admin par défaut:"
echo "  Username: admin"
echo "  Password: admin123"
echo "  Email: admin@archimeuble.com"
 
