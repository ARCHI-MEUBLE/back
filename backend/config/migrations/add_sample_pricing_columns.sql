-- Migration : Ajouter les colonnes de tarification aux échantillons
-- Date : 2026-01-08
-- Description : Ajoute price_per_m2 et unit_price aux tables sample_types et sample_colors

-- Ajouter les colonnes à sample_types (si elles n'existent pas déjà)
ALTER TABLE sample_types ADD COLUMN price_per_m2 REAL DEFAULT 0.0;
ALTER TABLE sample_types ADD COLUMN unit_price REAL DEFAULT 0.0;

-- Ajouter les colonnes à sample_colors (si elles n'existent pas déjà)
ALTER TABLE sample_colors ADD COLUMN price_per_m2 REAL DEFAULT 0.0;
ALTER TABLE sample_colors ADD COLUMN unit_price REAL DEFAULT 0.0;
