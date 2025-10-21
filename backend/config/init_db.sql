-- ArchiMeuble - Script SQL d'initialisation
-- Auteur : Collins
-- Date : 2025-10-20

-- Créer la table templates
CREATE TABLE IF NOT EXISTS templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    prompt TEXT NOT NULL,
    base_price REAL NOT NULL,
    image_url TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Créer la table configurations
CREATE TABLE IF NOT EXISTS configurations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_session TEXT NOT NULL,
    prompt TEXT NOT NULL,
    price REAL NOT NULL,
    glb_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insérer les 3 meubles TV
INSERT INTO templates (name, prompt, base_price, image_url) VALUES
('Meuble TV Scandinave', 'M1(1700,500,730)EFH3(F,T,F)', 899.00, '/frontend/assets/images/meuble-scandinave.jpg'),
('Meuble TV Moderne', 'M1(2000,400,600)EFH2(T,T)', 1099.00, '/frontend/assets/images/meuble-moderne.jpg'),
('Meuble TV Compact', 'M1(1200,350,650)EFH4(F,F,T,F)', 699.00, '/frontend/assets/images/meuble-compact.jpg');
