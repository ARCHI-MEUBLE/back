-- 0016 installation: in the last step of the configurator the customer chooses whether the furniture is installed and
-- assembled at home (config_data.features.installation). The service is priced like the furniture delivery, by m2 of
-- front (width x height) with a minimum per piece of furniture, and is part of the furniture price when chosen.
-- The delivery itself stays a separate fee added to the cart.
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('installation', 'furniture', 'price_per_m2', 50, 'eur_m2', 'Pose et montage d''un meuble chez le client, par m² de face avant (largeur × hauteur)'),
('installation', 'furniture', 'minimum', 90, 'eur', 'Montant minimal de la pose et du montage d''un meuble'),
('uncertainty', 'installation', 'relative', 0.15, 'ratio', 'Incertitude de la fourchette de prix sur la pose et le montage (0,15 = 15 %)')
ON CONFLICT (category, item_type, param_name) DO NOTHING;
