-- Migration pour ajouter la colonne category et config_data à la table models
-- Date: 2026-01-06

-- Ajouter la colonne category si elle n'existe pas
-- Note: SQLite ne supporte pas "IF NOT EXISTS" pour ADD COLUMN, on ignore l'erreur si elle existe déjà
ALTER TABLE models ADD COLUMN category TEXT;
ALTER TABLE models ADD COLUMN config_data TEXT;
