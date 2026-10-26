-- 0005 furniture pricing: a standard piece (bookcase, TV unit, sideboard) now lands around 1 000 - 1 250 EUR.
-- DECIMAL(10,4) rounded door coefficients such as 0.00004 down to 0.0000: widen the precision first.
ALTER TABLE pricing_config ALTER COLUMN param_value TYPE DECIMAL(14,6);

INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('casing', 'full', 'coefficient', 1.25, 'coefficient', 'Coefficient pour le caisson complet (majoration complexite)'),
('shelves', 'wood', 'price_per_m2', 130, 'eur_m2', 'Prix au m2 d''une etagere ou d''un montant en bois'),
('shelves', 'standard', 'price_per_m2', 130, 'eur_m2', 'Prix au m2 d''une etagere standard'),
('shelves', 'glass', 'price_per_m2', 300, 'eur_m2', 'Prix du verre au m2'),
('drawers', 'standard', 'base_price', 70, 'eur', 'Prix de base d''un tiroir standard'),
('drawers', 'standard', 'coefficient', 0.0001, 'coefficient', 'Coefficient (x largeur x profondeur en mm2)'),
('drawers', 'push', 'base_price', 90, 'eur', 'Prix de base d''un tiroir push'),
('drawers', 'push', 'coefficient', 0.00012, 'coefficient', 'Coefficient (x largeur x profondeur en mm2)'),
('doors', 'simple', 'coefficient', 0.00006, 'coefficient', 'Coefficient porte simple (x longueur x hauteur en mm2)'),
('doors', 'double', 'coefficient', 0.00012, 'coefficient', 'Coefficient double porte (x longueur x hauteur en mm2)'),
('doors', 'glass', 'coefficient', 0.00012, 'coefficient', 'Coefficient porte vitree (x longueur x hauteur en mm2)'),
('doors', 'push', 'coefficient', 0.00008, 'coefficient', 'Coefficient porte push (x longueur x hauteur en mm2)'),
('hinges', 'standard', 'price_per_unit', 8, 'eur', 'Prix d''une charniere standard'),
('handles', 'horizontal_bar', 'price_per_unit', 20, 'eur', 'Prix d''une poignee barre horizontale'),
('handles', 'vertical_bar', 'price_per_unit', 20, 'eur', 'Prix d''une poignee barre verticale'),
('handles', 'knob', 'price_per_unit', 12, 'eur', 'Prix d''un bouton de porte'),
('handles', 'recessed', 'price_per_unit', 25, 'eur', 'Prix d''une poignee encastree'),
('bases', 'metal', 'price_per_foot', 30, 'eur', 'Prix d''un pied metallique'),
('bases', 'wood', 'price_per_m3', 1200, 'eur_m3', 'Prix du bois pour socle au m3'),
('lighting', 'led', 'price_per_linear_meter', 30, 'eur_linear_m', 'Prix de la LED par metre lineaire'),
('cables', 'pass_cable', 'fixed_price', 15, 'eur', 'Prix fixe pour un passe-cable'),
('wardrobe', 'rod', 'price_per_linear_meter', 35, 'eur_linear_m', 'Prix de la barre de penderie par metre lineaire'),
('columns', 'standard', 'price_per_m2', 130, 'eur_m2', 'Prix au m2 d''une colonne'),
('materials', 'agglomere', 'price_per_m2', 110, 'eur_m2', 'Prix au m2 de l''agglomere'),
('materials', 'mdf_melamine', 'price_per_m2', 140, 'eur_m2', 'Prix au m2 du MDF melamine'),
('materials', 'plaque_bois', 'price_per_m2', 180, 'eur_m2', 'Prix au m2 du plaque bois')
ON CONFLICT (category, item_type, param_name) DO UPDATE
SET param_value = EXCLUDED.param_value, description = EXCLUDED.description;

-- The configurator prices the carcass with the price per m2 of the chosen colour: align every colour on its material.
UPDATE sample_colors sc
SET price_per_m2 = CASE
        WHEN st.material ILIKE 'agglom%' THEN 110
        WHEN st.material ILIKE '%mdf%' OR st.material ILIKE '%melamine%' THEN 140
        WHEN st.material ILIKE '%bois%' THEN 180
        ELSE 140
    END
FROM sample_types st
WHERE sc.type_id = st.id;
