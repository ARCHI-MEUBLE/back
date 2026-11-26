-- 0007 cleanup: drop the tables no code reads or writes any more, and replace the configurator price grid.
--   clients, projets, devis : first prototype's quote module, replaced by quote_requests / orders
--   sessions                : PHP sessions are stored on disk (storage/sessions)
--   templates               : furniture templates moved to "models"; the /templates route had no caller
--   saved_configurations    : configurations live in "configurations" (cart/order FKs were dropped in 0001)
--   saved_facades           : facade configurations are never saved outside the cart
--   pricing                 : per-m3 tariffs, superseded by pricing_config
DROP TABLE IF EXISTS devis, projets, clients, sessions, templates, saved_configurations, saved_facades, pricing CASCADE;

-- The configurator price is now an estimate with a range (price +/- standard deviation), computed by the backend
-- (FurniturePriceCalculator). A standard piece (TV unit, bookcase) lands around 1 200 - 1 400 EUR, larger ones more.
-- The previous grid (coefficients per mm2, glass shelves and glass doors) no longer applies: it is replaced as a whole.
DELETE FROM pricing_config;

INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('workshop', 'fixed', 'price', 600, 'eur', 'Forfait conception, decoupe, assemblage et finitions'),
('materials', 'agglomere', 'price_per_m2', 75, 'eur_m2', 'Panneau agglomere au m2 (caisson, fond, etageres, montants)'),
('materials', 'mdf_melamine', 'price_per_m2', 95, 'eur_m2', 'Panneau MDF melamine au m2'),
('materials', 'plaque_bois', 'price_per_m2', 125, 'eur_m2', 'Panneau plaque bois au m2'),
('panels', 'back', 'surface_factor', 0.5, 'coefficient', 'Part du prix au m2 comptee pour le fond, plus fin'),
('fronts', 'door', 'fixed_price', 65, 'eur', 'Charnieres et pose, par battant de porte'),
('fronts', 'drawer', 'fixed_price', 110, 'eur', 'Coulisses et caisson, par tiroir'),
('fronts', 'push', 'supplement', 30, 'eur', 'Mecanisme push (porte ou tiroir sans poignee)'),
('fronts', 'facade', 'material_factor', 1.3, 'coefficient', 'Majoration du prix au m2 pour les facades (chants, finition)'),
('handles', 'vertical_bar', 'price_per_unit', 25, 'eur', 'Poignee barre verticale'),
('handles', 'horizontal_bar', 'price_per_unit', 25, 'eur', 'Poignee barre horizontale'),
('handles', 'knob', 'price_per_unit', 15, 'eur', 'Bouton'),
('handles', 'recessed', 'price_per_unit', 30, 'eur', 'Poignee encastree'),
('options', 'dressing_rod', 'price_per_linear_meter', 45, 'eur_linear_m', 'Tringle de penderie au metre'),
('options', 'led', 'price_per_linear_meter', 60, 'eur_linear_m', 'Eclairage LED au metre'),
('options', 'cable_hole', 'fixed_price', 20, 'eur', 'Passe-cable'),
('bases', 'metal', 'price_per_foot', 20, 'eur', 'Pied metallique'),
('bases', 'metal', 'foot_spacing_mm', 1000, 'mm', 'Ecart maximal entre deux paires de pieds'),
('bases', 'wood', 'price_per_linear_meter', 110, 'eur_linear_m', 'Socle bois au metre de largeur'),
('delivery', 'handling', 'price_per_m2', 45, 'eur_m2', 'Livraison et manutention, par m2 de facade du meuble'),
('uncertainty', 'workshop', 'relative', 0.10, 'ratio', 'Incertitude relative (ecart type) du forfait atelier'),
('uncertainty', 'panels', 'relative', 0.10, 'ratio', 'Incertitude relative (ecart type) des panneaux'),
('uncertainty', 'fronts', 'relative', 0.15, 'ratio', 'Incertitude relative (ecart type) des portes et tiroirs'),
('uncertainty', 'hardware', 'relative', 0.12, 'ratio', 'Incertitude relative (ecart type) des poignees, socle et options'),
('uncertainty', 'delivery', 'relative', 0.30, 'ratio', 'Incertitude relative (ecart type) de la livraison');
