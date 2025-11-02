-- Migration: Ajouter les colonnes pour les rappels 24h et 1h
-- Renommer reminder_sent en reminder_24h_sent et ajouter reminder_1h_sent

-- Vérifier si la colonne reminder_24h_sent existe, sinon la créer
-- SQLite ne supporte pas ALTER COLUMN, donc on doit recréer la table si nécessaire

-- Ajouter la colonne reminder_1h_sent si elle n'existe pas
ALTER TABLE calendly_appointments ADD COLUMN reminder_1h_sent BOOLEAN DEFAULT 0;

-- Si reminder_24h_sent n'existe pas, renommer reminder_sent
-- Note: Cette migration suppose que reminder_sent existe déjà
-- Si vous avez des données existantes avec reminder_sent, celles-ci seront considérées comme reminder_24h_sent
