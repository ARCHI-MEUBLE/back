-- Ajouter la colonne dxf_url à la table configurations
ALTER TABLE configurations ADD COLUMN dxf_url TEXT DEFAULT NULL;

-- Index pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_configurations_dxf_url ON configurations(dxf_url);
