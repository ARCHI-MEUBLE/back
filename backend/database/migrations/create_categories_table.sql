-- Migration : Création de la table categories
-- Date : 2026-01-08

CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    slug TEXT NOT NULL UNIQUE,
    description TEXT,
    image_url TEXT,
    display_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertion des catégories par défaut
INSERT INTO categories (name, slug, description, display_order, is_active) VALUES
('Dressing', 'dressing', 'Optimisez chaque centimètre', 1, TRUE),
('Bibliothèque', 'bibliotheque', 'Du sol au plafond', 2, TRUE),
('Buffet', 'buffet', 'Élégance fonctionnelle', 3, TRUE),
('Bureau', 'bureau', 'Votre espace de travail', 4, TRUE),
('Meuble TV', 'meuble-tv', 'Lignes épurées', 5, TRUE),
('Sous-escalier', 'sous-escalier', 'Chaque recoin optimisé', 6, TRUE),
('Tête de lit', 'tete-de-lit', 'Confort et style', 7, TRUE)
ON CONFLICT DO NOTHING;
