-- Migration : Création de la table categories
-- Date : 2026-01-08

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    slug TEXT NOT NULL UNIQUE,
    description TEXT,
    image_url TEXT,
    display_order INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insertion des catégories par défaut
INSERT OR IGNORE INTO categories (name, slug, description, display_order, is_active) VALUES
('Dressing', 'dressing', 'Optimisez chaque centimètre', 1, 1),
('Bibliothèque', 'bibliotheque', 'Du sol au plafond', 2, 1),
('Buffet', 'buffet', 'Élégance fonctionnelle', 3, 1),
('Bureau', 'bureau', 'Votre espace de travail', 4, 1),
('Meuble TV', 'meuble-tv', 'Lignes épurées', 5, 1),
('Sous-escalier', 'sous-escalier', 'Chaque recoin optimisé', 6, 1),
('Tête de lit', 'tete-de-lit', 'Confort et style', 7, 1);
