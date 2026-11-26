-- 0012 commerce prices: facade settings seeded, admin price adjustment on configurations, delivery billed apart.

-- Facade settings were only created by the test fixtures: on a database built by the migrations the admin could not
-- save any of them (the PUT route needs an existing row). Seed every key the code reads, with the values the code
-- falls back to. Existing rows (set by the admin) are kept.
INSERT INTO facade_settings (setting_key, setting_value, description) VALUES
('material_price_per_m2', '150', 'Prix au m² utilisé seulement si aucun matériau n''est proposé ou si le matériau choisi n''a pas de prix'),
('hinge_base_price', '34.20', 'Prix d''une charnière (trou + charnière fournie), en euros TTC'),
('hinge_coefficient', '0.05', 'Supplément par charnière, en part du prix de la façade (0,05 = 5 % par charnière ; 0 = aucun supplément)'),
('max_width', '600', 'Largeur maximale d''une façade, en mm'),
('max_height', '2300', 'Hauteur maximale d''une façade, en mm'),
('fixed_depth', '19', 'Épaisseur des façades, en mm (imposée à toutes les façades commandées)'),
('hinge_edge_margin', '150', 'Distance entre les trous de charnières extrêmes et les bords haut et bas, en mm'),
('hinge_hole_diameter', '26', 'Diamètre des trous de charnières, en mm')
ON CONFLICT (setting_key) DO NOTHING;

-- Delivery is now billed apart from the furniture price (orders.shipping_amount). Configurations saved before this
-- migration have a price that already contains the old "delivery and handling" line: they are flagged so that no
-- delivery is charged a second time for them.
ALTER TABLE configurations ADD COLUMN IF NOT EXISTS price_includes_delivery BOOLEAN NOT NULL DEFAULT FALSE;
UPDATE configurations SET price_includes_delivery = TRUE;

-- The admin can set the price of a configuration by hand (discount, negotiated price). The flag keeps that price
-- when the admin saves the configuration again; a save by the customer recomputes the price and clears it.
ALTER TABLE configurations ADD COLUMN IF NOT EXISTS price_adjusted BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE configurations ADD COLUMN IF NOT EXISTS price_note TEXT NULL;

-- Delivery fees computed by the server when the order is created; total_amount includes them.
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0;
