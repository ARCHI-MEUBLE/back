-- Script d'initialisation de la base de données ArchiMeuble
-- SQLite3 - Schéma complet

-- Table des modèles de meubles
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

-- Table des administrateurs
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    email TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

-- Table des clients (CRM)
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

-- Table des projets (CRM)
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

-- Insérer un admin par défaut (mot de passe: admin123)
-- Hash bcrypt de "admin123"
INSERT OR IGNORE INTO admins (username, password, email)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@archimeuble.com');

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
    meeting_url TEXT,
    phone_number TEXT,
    status TEXT DEFAULT 'scheduled',
    confirmation_sent BOOLEAN DEFAULT 0,
    reminder_24h_sent BOOLEAN DEFAULT 0,
    reminder_1h_sent BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Index pour optimiser les requêtes Calendly
CREATE INDEX IF NOT EXISTS idx_calendly_start_time ON calendly_appointments(start_time);
CREATE INDEX IF NOT EXISTS idx_calendly_status ON calendly_appointments(status);
CREATE INDEX IF NOT EXISTS idx_calendly_reminder_24h_sent ON calendly_appointments(reminder_24h_sent);
CREATE INDEX IF NOT EXISTS idx_calendly_reminder_1h_sent ON calendly_appointments(reminder_1h_sent);
CREATE INDEX IF NOT EXISTS idx_calendly_email ON calendly_appointments(client_email);

-- Table panier (cart_items)
CREATE TABLE IF NOT EXISTS cart_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    configuration_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (configuration_id) REFERENCES configurations(id) ON DELETE CASCADE,
    UNIQUE(customer_id, configuration_id)
);

CREATE INDEX IF NOT EXISTS idx_cart_customer ON cart_items(customer_id);
CREATE INDEX IF NOT EXISTS idx_cart_configuration ON cart_items(configuration_id);

-- Insérer quelques modèles de test
INSERT OR IGNORE INTO models (id, name, prompt, description, price) VALUES
(1, 'Meuble Scandinave 3 modules', 'M1(1700,500,730)EFH3(F,T,F)', 'Meuble style scandinave avec 3 modules, largeur 1700mm', 450.00),
(2, 'Meuble Scandinave 4 modules', 'M1(2000,400,600)EFH4(T,T,F,F)', 'Meuble style scandinave avec 4 modules, largeur 2000mm', 580.00),
(3, 'Meuble Compact 2 modules', 'M1(1200,350,650)EFH2(F,T)', 'Meuble compact avec 2 modules, largeur 1200mm', 320.00);
