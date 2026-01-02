-- Migration: Ajout colonne status pour le nouveau flux de validation
-- Date: 2025-12-27
-- Description: Permet de gérer l'état des configurations (en attente, validée, payée, etc.)

-- Ajouter la colonne status avec valeur par défaut
ALTER TABLE configurations ADD COLUMN status TEXT DEFAULT 'en_attente_validation';

-- Mettre à jour les configurations existantes (si nécessaire)
UPDATE configurations SET status = 'en_attente_validation' WHERE status IS NULL;

-- Créer un index pour optimiser les requêtes par statut
CREATE INDEX IF NOT EXISTS idx_configurations_status ON configurations(status);
