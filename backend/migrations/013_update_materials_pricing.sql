-- Migration pour remplacer price_modifier par price_per_m2 dans facade_materials
-- Créé le 2026-01-12

-- Ajouter la colonne price_per_m2
ALTER TABLE facade_materials ADD COLUMN price_per_m2 DECIMAL(10, 2) DEFAULT 150.00;

-- Migrer les anciennes données : utiliser le prix global + modifier comme prix au m²
-- Pour les matériaux existants, on prend 150 €/m² comme base
UPDATE facade_materials 
SET price_per_m2 = CASE 
    WHEN price_modifier > 0 THEN 150.00 + (price_modifier / 2)  -- Augmenter le prix de base
    WHEN price_modifier < 0 THEN 150.00 + (price_modifier / 2)  -- Diminuer le prix de base
    ELSE 150.00
END;

-- Les valeurs initiales deviennent :
-- Chêne Naturel (0) : 150 €/m²
-- Chêne Foncé (+10) : 155 €/m²
-- Blanc Mat (-5) : 147.50 €/m²
-- Noir Mat (+5) : 152.50 €/m²
-- Gris Anthracite (0) : 150 €/m²
-- Bleu Pastel (+15) : 157.50 €/m²
-- Vert Sauge (+15) : 157.50 €/m²

-- Note: On garde price_modifier pour compatibilité mais il ne sera plus utilisé dans les calculs
