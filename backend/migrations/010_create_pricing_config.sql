-- Migration 010: Système complet de configuration des prix
-- Cette table remplace tous les prix hardcodés dans le frontend

CREATE TABLE IF NOT EXISTS pricing_config (
    id SERIAL PRIMARY KEY,
    category TEXT NOT NULL,        -- Catégorie principale (materials, drawers, shelves, etc.)
    item_type TEXT NOT NULL,       -- Type spécifique dans la catégorie
    param_name TEXT NOT NULL,      -- Nom du paramètre (base_price, coefficient, price_per_m2, etc.)
    param_value DECIMAL(10,2) NOT NULL,     -- Valeur numérique du paramètre
    unit TEXT NOT NULL,            -- Unité (eur, eur_m2, eur_m3, coefficient, eur_linear_m, meters)
    description TEXT,              -- Description pour l'interface admin
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(category, item_type, param_name)
);

-- Index pour optimiser les requêtes
CREATE INDEX IF NOT EXISTS idx_pricing_config_category ON pricing_config(category);
CREATE INDEX IF NOT EXISTS idx_pricing_config_active ON pricing_config(is_active);
CREATE INDEX IF NOT EXISTS idx_pricing_config_lookup ON pricing_config(category, item_type, param_name);

-- ============================================================================
-- DONNÉES PAR DÉFAUT (basées sur les prix actuellement hardcodés)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. MATÉRIAUX - Suppléments de prix
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('materials', 'agglomere', 'supplement', 0, 'eur', 'Supplément pour aggloméré'),
('materials', 'agglomere', 'price_per_m2', 50, 'eur_m2', 'Prix au m² de l''aggloméré'),
('materials', 'mdf_melamine', 'supplement', 70, 'eur', 'Supplément pour MDF mélaminé'),
('materials', 'mdf_melamine', 'price_per_m2', 80, 'eur_m2', 'Prix au m² du MDF mélaminé'),
('materials', 'plaque_bois', 'supplement', 140, 'eur', 'Supplément pour plaqué bois'),
('materials', 'plaque_bois', 'price_per_m2', 150, 'eur_m2', 'Prix au m² du plaqué bois');

-- ----------------------------------------------------------------------------
-- 2. TIROIRS - Prix de base + coefficient × largeur × profondeur
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('drawers', 'standard', 'base_price', 35, 'eur', 'Prix de base d''un tiroir standard'),
('drawers', 'standard', 'coefficient', 0.0001, 'coefficient', 'Coefficient (× largeur × profondeur en mm²)'),
('drawers', 'push', 'base_price', 45, 'eur', 'Prix de base d''un tiroir push'),
('drawers', 'push', 'coefficient', 0.0001, 'coefficient', 'Coefficient (× largeur × profondeur en mm²)');

-- ----------------------------------------------------------------------------
-- 3. ÉTAGÈRES EN VERRE - Prix du verre × surface
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('shelves', 'glass', 'price_per_m2', 250, 'eur_m2', 'Prix du verre au m²');

-- ----------------------------------------------------------------------------
-- 4. ÉTAGÈRES NORMALES - Profondeur × largeur × prix m² × nombre
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('shelves', 'standard', 'price_per_m2', 100, 'eur_m2', 'Prix au m² d''une étagère standard (utilise le matériau)');

-- ----------------------------------------------------------------------------
-- 5. ÉCLAIRAGE LED - Prix de la LED × largeur
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('lighting', 'led', 'price_per_linear_meter', 15, 'eur_linear_m', 'Prix de la LED par mètre linéaire');

-- ----------------------------------------------------------------------------
-- 6. PASSE-CÂBLE - Prix fixe
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('cables', 'pass_cable', 'fixed_price', 10, 'eur', 'Prix fixe pour un passe-câble');

-- ----------------------------------------------------------------------------
-- 7. SOCLES - 3 types différents
-- ----------------------------------------------------------------------------
-- Pas de socle
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('bases', 'none', 'fixed_price', 0, 'eur', 'Pas de socle');

-- Caisson en bois : coefficient × largeur × profondeur
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('bases', 'wood', 'coefficient', 0.0001, 'coefficient', 'Coefficient (× largeur × profondeur en mm²)');

-- Pied métal : 2 pieds tous les 2 mètres
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('bases', 'metal', 'price_per_foot', 20, 'eur', 'Prix d''un pied métallique'),
('bases', 'metal', 'foot_interval', 2000, 'mm', 'Intervalle : 2 pieds tous les 2000mm (2m)');

-- ----------------------------------------------------------------------------
-- 8. CHARNIÈRES - Prix × nombre
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('hinges', 'standard', 'price_per_unit', 5, 'eur', 'Prix d''une charnière standard');

-- ----------------------------------------------------------------------------
-- 9. PORTES - Coefficient × longueur × hauteur + prix des charnières
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('doors', 'simple', 'coefficient', 0.00004, 'coefficient', 'Coefficient porte simple (× longueur × hauteur en mm²)'),
('doors', 'simple', 'hinge_count', 2, 'units', 'Nombre de charnières pour une porte simple'),
('doors', 'double', 'coefficient', 0.00008, 'coefficient', 'Coefficient double porte (× longueur × hauteur en mm²)'),
('doors', 'double', 'hinge_count', 4, 'units', 'Nombre de charnières pour une double porte'),
('doors', 'glass', 'coefficient', 0.00009, 'coefficient', 'Coefficient porte vitrée (× longueur × hauteur en mm²)'),
('doors', 'glass', 'hinge_count', 2, 'units', 'Nombre de charnières pour une porte vitrée'),
('doors', 'push', 'coefficient', 0.00005, 'coefficient', 'Coefficient porte push (× longueur × hauteur en mm²)'),
('doors', 'push', 'hinge_count', 2, 'units', 'Nombre de charnières pour une porte push');

-- ----------------------------------------------------------------------------
-- 10. COLONNES - Profondeur × hauteur × prix m² × nombre
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('columns', 'standard', 'price_per_m2', 120, 'eur_m2', 'Prix au m² d''une colonne (utilise le matériau)');

-- ----------------------------------------------------------------------------
-- 11. CAISSON COMPLET - Surface totale × coefficient × prix m²
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('casing', 'full', 'coefficient', 1.2, 'coefficient', 'Coefficient pour le caisson complet (majoration complexité)');

-- ----------------------------------------------------------------------------
-- 12. PENDERIE - Prix au mètre linéaire
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('wardrobe', 'rod', 'price_per_linear_meter', 20, 'eur_linear_m', 'Prix de la barre de penderie par mètre linéaire');

-- ----------------------------------------------------------------------------
-- 13. POIGNÉES - Prix fixe par type
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('handles', 'horizontal_bar', 'price_per_unit', 15, 'eur', 'Prix d''une poignée barre horizontale'),
('handles', 'vertical_bar', 'price_per_unit', 15, 'eur', 'Prix d''une poignée barre verticale'),
('handles', 'knob', 'price_per_unit', 10, 'eur', 'Prix d''un bouton de porte'),
('handles', 'recessed', 'price_per_unit', 20, 'eur', 'Prix d''une poignée encastrée');

-- ----------------------------------------------------------------------------
-- 14. SOCLE BOIS - Paramètres complémentaires
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('bases', 'wood', 'price_per_m3', 800, 'eur_m3', 'Prix du bois pour socle au m³'),
('bases', 'wood', 'height', 80, 'mm', 'Hauteur fixe du socle bois'),
('bases', 'metal', 'base_foot_count', 2, 'units', 'Nombre minimum de pieds (base)');

-- ----------------------------------------------------------------------------
-- 15. AFFICHAGE PRIX - Paramètres d'affichage
-- ----------------------------------------------------------------------------
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('display', 'price', 'display_mode', 0, 'units', 'Mode d''affichage (0=Direct, 1=Intervalle)'),
('display', 'price', 'deviation_range', 100, 'eur', 'Écart pour l''affichage en intervalle');

-- ============================================================================
-- Trigger pour mise à jour automatique de updated_at
-- ============================================================================
CREATE OR REPLACE FUNCTION update_pricing_config_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS update_pricing_config_timestamp ON pricing_config;
CREATE TRIGGER update_pricing_config_timestamp
BEFORE UPDATE ON pricing_config
FOR EACH ROW
EXECUTE FUNCTION update_pricing_config_timestamp();
