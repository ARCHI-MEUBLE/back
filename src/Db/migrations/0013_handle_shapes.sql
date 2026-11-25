-- 0013 handle shapes: the configurator also offers cup handles, round recessed pulls and leather straps, the main
-- families of furniture handles. A recessed pull has no length, like a knob.
ALTER TABLE handle_models DROP CONSTRAINT IF EXISTS handle_models_shape_check;
ALTER TABLE handle_models ADD CONSTRAINT handle_models_shape_check
    CHECK (shape IN ('bar', 'flat_bar', 'profile', 'knob', 'cup', 'recessed', 'leather'));

INSERT INTO handle_models (name, shape, finish_hex, length_mm, price_per_unit, position) VALUES
('Bouton chromé', 'knob', '#D8DADC', NULL, 15, 4),
('Cuvette inox', 'cup', '#C9CCCF', 128, 28, 5),
('Cuvette noire', 'cup', '#1C1C1C', 128, 28, 6),
('Encastrée ronde laiton', 'recessed', '#B08D57', NULL, 22, 7),
('Lanière cuir', 'leather', '#7A4B2A', 160, 24, 8)
ON CONFLICT DO NOTHING;
