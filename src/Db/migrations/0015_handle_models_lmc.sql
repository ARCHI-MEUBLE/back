-- 0015 handle models taken from the LMC catalogue (lmcstore.com), with the dimensions of their reference tables, written
-- there as "centre distance (length x width x projection)" in mm:
--   length_mm           overall length of the handle;
--   center_distance_mm  distance between its two screws, the holes drilled in the door (entraxe);
--   width_mm            width seen from the front (diameter of a round bar, width of a strap or of a cup);
--   projection_mm       how far it stands out from the door.
-- A model without them is drawn with the usual proportions of its shape. The LMC pages show no price: the prices
-- below are ours.
ALTER TABLE handle_models
    ADD COLUMN IF NOT EXISTS center_distance_mm INTEGER NULL
        CHECK (center_distance_mm IS NULL OR center_distance_mm BETWEEN 10 AND 800),
    ADD COLUMN IF NOT EXISTS width_mm INTEGER NULL CHECK (width_mm IS NULL OR width_mm BETWEEN 2 AND 100),
    ADD COLUMN IF NOT EXISTS projection_mm INTEGER NULL CHECK (projection_mm IS NULL OR projection_mm BETWEEN 0 AND 100);

UPDATE handle_models SET name = 'Cuvette LIVING inox brossé', length_mm = 92, width_mm = 27 WHERE name = 'Cuvette inox';
UPDATE handle_models SET name = 'Cuvette LIVING noire', length_mm = 92, width_mm = 27 WHERE name = 'Cuvette noire';

INSERT INTO handle_models (name, shape, finish_hex, length_mm, center_distance_mm, width_mm, projection_mm, price_per_unit, position) VALUES
('Fil Ø10 aluminium anodisé, entraxe 128', 'bar', '#B8BBBE', 138, 128, 10, 30, 24, 9),
('Fil Ø10 aluminium anodisé, entraxe 192', 'bar', '#B8BBBE', 202, 192, 10, 30, 27, 10),
('FLAT chromé, entraxe 128', 'flat_bar', '#D9DCDF', 143, 128, 8, 34, 26, 11),
('FLAT nickelé mat, entraxe 192', 'flat_bar', '#B5B7B9', 207, 192, 8, 34, 29, 12),
('COMPACT noir, entraxe 160', 'flat_bar', '#1A1A1A', 172, 160, 8, 36, 27, 13),
('LOGGIC laiton noir, entraxe 200', 'flat_bar', '#1C1C1C', 223, 200, NULL, NULL, 38, 14),
('SAFARI cuir marron clair, entraxe 160', 'leather', '#A9703F', 224, 160, 24, 25, 34, 15),
('HEXXA noir mat, longueur 200', 'profile', '#222222', 200, NULL, NULL, NULL, 32, 16)
ON CONFLICT DO NOTHING;
