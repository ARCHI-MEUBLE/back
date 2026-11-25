-- 0011 pricing admin: every setting of the furniture price lives in the database and is editable from the admin.
--   material_prices : price per m2 of each material of the sample admin (sample_types.material). It replaces the three
--                     hard-coded material rows of the grid; a material without a price uses materials.default.
--   shipping        : delivery leaves the furniture price and becomes a separate charge of the cart and the order.
--   new settings    : price rounding, minimum pairs of metal feet, flush mounting, glass doors, default deposit.
CREATE TABLE IF NOT EXISTS material_prices (
    id SERIAL PRIMARY KEY,
    material TEXT NOT NULL,
    price_per_m2 DECIMAL(10,2) NOT NULL CHECK (price_per_m2 >= 0),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- The configurator links a colour to its material by name, whatever the case.
CREATE UNIQUE INDEX IF NOT EXISTS material_prices_material_key ON material_prices (LOWER(material));

-- One row per material of the sample admin, priced as the previous rule did: a name containing "aggl" took the
-- agglomerate price, one containing "bois" the wood veneer price, any other one the melamine MDF price.
INSERT INTO material_prices (material, price_per_m2)
SELECT DISTINCT ON (LOWER(TRIM(st.material))) TRIM(st.material),
    CASE
        WHEN LOWER(st.material) LIKE '%aggl%' THEN COALESCE((SELECT param_value FROM pricing_config
            WHERE category = 'materials' AND item_type = 'agglomere' AND param_name = 'price_per_m2'), 75)
        WHEN LOWER(st.material) LIKE '%bois%' THEN COALESCE((SELECT param_value FROM pricing_config
            WHERE category = 'materials' AND item_type = 'plaque_bois' AND param_name = 'price_per_m2'), 125)
        ELSE COALESCE((SELECT param_value FROM pricing_config
            WHERE category = 'materials' AND item_type = 'mdf_melamine' AND param_name = 'price_per_m2'), 95)
    END
FROM sample_types st
WHERE TRIM(st.material) <> ''
ORDER BY LOWER(TRIM(st.material)), st.id
ON CONFLICT DO NOTHING;

-- A material without a price (created in the sample admin, not priced yet) costs what any unknown material cost so far.
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description)
VALUES ('materials', 'default', 'price_per_m2', COALESCE((SELECT param_value FROM pricing_config
    WHERE category = 'materials' AND item_type = 'mdf_melamine' AND param_name = 'price_per_m2'), 95), 'eur_m2', '')
ON CONFLICT (category, item_type, param_name) DO NOTHING;

DELETE FROM pricing_config WHERE category = 'materials' AND NOT (item_type = 'default' AND param_name = 'price_per_m2');

-- Delivery is now charged apart from the furniture: the row keeps its id and value, and leaves the price range.
UPDATE pricing_config SET category = 'shipping', item_type = 'furniture'
WHERE category = 'delivery' AND item_type = 'handling' AND param_name = 'price_per_m2'
  AND NOT EXISTS (SELECT 1 FROM pricing_config
      WHERE category = 'shipping' AND item_type = 'furniture' AND param_name = 'price_per_m2');

DELETE FROM pricing_config WHERE category = 'delivery' AND item_type = 'handling' AND param_name = 'price_per_m2';
DELETE FROM pricing_config WHERE category = 'uncertainty' AND item_type = 'delivery' AND param_name = 'relative';

-- Every setting the calculator reads (FurniturePricingGrid::DEFAULTS) exists in the database, active, with a description
-- a shopkeeper understands. Existing values are kept; missing rows get the default value.
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('workshop', 'fixed', 'price', 600, 'eur', 'Forfait par meuble : conception, découpe, assemblage et finitions'),
('materials', 'default', 'price_per_m2', 95, 'eur_m2', 'Prix au m² d''une matière qui n''a pas encore de prix dans « Prix des matières »'),
('panels', 'back', 'surface_factor', 0.5, 'coefficient', 'Part du prix au m² comptée pour le fond, plus fin que les autres panneaux (0,5 = moitié prix)'),
('fronts', 'door', 'fixed_price', 65, 'eur', 'Charnières et pose, par battant de porte'),
('fronts', 'drawer', 'fixed_price', 110, 'eur', 'Coulisses et caisson, par tiroir'),
('fronts', 'push', 'supplement', 30, 'eur', 'Ouverture par pression (push), par porte ou tiroir : remplace la poignée'),
('fronts', 'facade', 'material_factor', 1.3, 'coefficient', 'Majoration du prix au m² de la matière pour les façades, chants et finition compris (1,3 = +30 %)'),
('fronts', 'glass', 'supplement_per_m2', 0, 'eur_m2', 'Supplément verre d''une porte vitrée, par m² de porte'),
('handles', 'vertical_bar', 'price_per_unit', 25, 'eur', 'Ancienne poignée barre verticale (meubles enregistrés avant les modèles de poignées)'),
('handles', 'horizontal_bar', 'price_per_unit', 25, 'eur', 'Ancienne poignée barre horizontale (meubles enregistrés avant les modèles de poignées)'),
('handles', 'knob', 'price_per_unit', 15, 'eur', 'Ancien bouton de porte (meubles enregistrés avant les modèles de poignées)'),
('handles', 'recessed', 'price_per_unit', 30, 'eur', 'Ancienne poignée encastrée (meubles enregistrés avant les modèles de poignées)'),
('options', 'dressing_rod', 'price_per_linear_meter', 45, 'eur_linear_m', 'Tringle de penderie, par mètre de largeur du compartiment'),
('options', 'led', 'price_per_linear_meter', 60, 'eur_linear_m', 'Éclairage LED, par mètre de largeur du compartiment'),
('options', 'cable_hole', 'fixed_price', 20, 'eur', 'Passe-câble, par compartiment'),
('options', 'flush_mounting', 'supplement', 0, 'eur', 'Supplément par meuble pour des façades encastrées entre les panneaux'),
('bases', 'metal', 'price_per_foot', 20, 'eur', 'Pied métallique, prix par pied'),
('bases', 'metal', 'foot_spacing_mm', 1000, 'mm', 'Écart maximal entre deux paires de pieds : une paire de plus au-delà'),
('bases', 'metal', 'min_pairs', 2, 'count', 'Nombre minimal de paires de pieds par meuble'),
('bases', 'wood', 'price_per_linear_meter', 110, 'eur_linear_m', 'Socle en bois, par mètre de largeur du meuble'),
('shipping', 'furniture', 'price_per_m2', 45, 'eur_m2', 'Livraison et manutention d''un meuble, par m² de face avant (largeur × hauteur)'),
('shipping', 'furniture', 'minimum', 0, 'eur', 'Montant minimal de la livraison d''un meuble (0 = pas de minimum)'),
('shipping', 'parcel', 'flat', 0, 'eur', 'Envoi en colis des façades, articles du catalogue et échantillons : forfait par commande'),
('shipping', 'parcel', 'free_from', 0, 'eur', 'Envoi en colis offert à partir de ce montant d''articles (0 = jamais offert)'),
('rounding', 'price', 'step', 10, 'eur', 'Arrondi du prix affiché et de sa fourchette (10 = à la dizaine d''euros)'),
('payment', 'deposit', 'default_percent', 30, 'percent', 'Acompte proposé par défaut, en pourcentage de la commande'),
('uncertainty', 'workshop', 'relative', 0.15, 'ratio', 'Incertitude de la fourchette de prix sur le forfait atelier (0,15 = 15 %)'),
('uncertainty', 'panels', 'relative', 0.15, 'ratio', 'Incertitude de la fourchette de prix sur les panneaux (0,15 = 15 %)'),
('uncertainty', 'fronts', 'relative', 0.25, 'ratio', 'Incertitude de la fourchette de prix sur les portes et tiroirs (0,25 = 25 %)'),
('uncertainty', 'hardware', 'relative', 0.20, 'ratio', 'Incertitude de la fourchette de prix sur les poignées, le socle et les options (0,20 = 20 %)')
ON CONFLICT (category, item_type, param_name) DO UPDATE
SET unit = EXCLUDED.unit, description = EXCLUDED.description, is_active = TRUE;

-- Deactivating a row used to bring back a hard-coded value without telling anyone: every row is active again.
UPDATE pricing_config SET is_active = TRUE WHERE is_active IS DISTINCT FROM TRUE;
