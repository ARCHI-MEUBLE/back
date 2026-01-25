-- Migration: Ajouter support pour plusieurs images par réalisation
-- Date: 2026-01-13

-- Table pour stocker plusieurs images par réalisation
CREATE TABLE IF NOT EXISTS realisation_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    realisation_id INTEGER NOT NULL,
    image_url TEXT NOT NULL,
    ordre INTEGER DEFAULT 0,
    legende TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (realisation_id) REFERENCES realisations(id) ON DELETE CASCADE
);

-- Index pour accélérer les requêtes
CREATE INDEX IF NOT EXISTS idx_realisation_images_realisation_id ON realisation_images(realisation_id);
CREATE INDEX IF NOT EXISTS idx_realisation_images_ordre ON realisation_images(realisation_id, ordre);

-- Migrer les images existantes depuis la table realisations
INSERT INTO realisation_images (realisation_id, image_url, ordre)
SELECT id, image_url, 0
FROM realisations
WHERE image_url IS NOT NULL AND image_url != '';
