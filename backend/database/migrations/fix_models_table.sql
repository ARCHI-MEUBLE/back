-- Migration pour ajouter la colonne category et config_data à la table models
-- Date: 2026-01-06

-- Ajouter la colonne category si elle n'existe pas
-- Note: PostgreSQL supports ADD COLUMN IF NOT EXISTS for safe migrations
ALTER TABLE models ADD COLUMN IF NOT EXISTS category TEXT;
ALTER TABLE models ADD COLUMN IF NOT EXISTS config_data TEXT;
