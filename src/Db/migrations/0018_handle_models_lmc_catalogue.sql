-- 0018 LMC handle catalogue: every size and finish of the LMC families already offered (Fil 10, FLAT, COMPACT, LOGGIC,
-- SAFARI, HEXXA, cup LIVING), read on lmcstore.com on 2026-11-27, with the photo of each finish (copied to the website, front public/images/handles/lmc) shown in the configurator
-- instead of the drawing. The same rows are in the Excel file given to the admin, which re-imports it to change them.
--   reference     LMC article number, used to find a model again when the Excel file is imported;
--   supplier_url  LMC product page.
-- HEXXA exists in 1100 mm: the length limit goes from 800 to 1200 mm. Prices, names and the active flag of the rows
-- that already exist are kept (the admin may have changed them); their dimensions, photo and order follow the catalogue.
-- The generic models without a reference move after the LMC ones.
ALTER TABLE handle_models
    ADD COLUMN IF NOT EXISTS reference VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS supplier_url VARCHAR(500) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS handle_models_reference_key ON handle_models (reference) WHERE reference IS NOT NULL;
ALTER TABLE handle_models DROP CONSTRAINT IF EXISTS handle_models_length_mm_check;
ALTER TABLE handle_models ADD CONSTRAINT handle_models_length_mm_check CHECK (length_mm IS NULL OR length_mm BETWEEN 20 AND 1200);

UPDATE handle_models h SET reference = v.reference
FROM (VALUES
    ('Fil Ø10 aluminium anodisé, entraxe 96', 'POI9409106AL'),
    ('Fil Ø10 aluminium anodisé, entraxe 128', 'POI9409138AL'),
    ('Fil Ø10 aluminium anodisé, entraxe 160', 'POI9409170AL'),
    ('Fil Ø10 aluminium anodisé, entraxe 192', 'POI9409202AL'),
    ('Fil Ø10 aluminium anodisé, entraxe 256', 'POI9409266AL'),
    ('Fil Ø10 aluminium anodisé, entraxe 352', 'POI9409362AL'),
    ('FLAT chromé, entraxe 128', 'POI518128CH'),
    ('FLAT chromé, entraxe 192', 'POI518192CH'),
    ('FLAT chromé, entraxe 256', 'POI518256CH'),
    ('FLAT nickelé mat, entraxe 128', 'POI518128NM'),
    ('FLAT nickelé mat, entraxe 192', 'POI518192NM'),
    ('FLAT nickelé mat, entraxe 256', 'POI518256NM'),
    ('FLAT nickelé mat, entraxe 448', 'POI518448NM'),
    ('COMPACT nickelé mat, entraxe 160', 'POI286160NM'),
    ('COMPACT nickelé mat, entraxe 320', 'POI286320NM'),
    ('COMPACT noir, entraxe 160', 'POI286160NO'),
    ('COMPACT noir, entraxe 320', 'POI286320NO'),
    ('LOGGIC laiton noir, entraxe 200', 'POI117200NO'),
    ('LOGGIC laiton or satiné, entraxe 200', 'POI117200OR'),
    ('SAFARI cuir marron clair, entraxe 160', 'POI8160160MC'),
    ('SAFARI cuir noir, entraxe 160', 'POI8160160NO'),
    ('HEXXA blanc, longueur 200', 'POI449200BL'),
    ('HEXXA blanc, longueur 1100', 'POI4491100BL'),
    ('HEXXA brun, longueur 200', 'POI449200BR'),
    ('HEXXA brun, longueur 1100', 'POI4491100BR'),
    ('HEXXA noir mat, longueur 200', 'POI449200NO'),
    ('HEXXA noir mat, longueur 1100', 'POI4491100NO'),
    ('Cuvette LIVING inox brossé', 'POI447IN'),
    ('Cuvette LIVING noire', 'POI447NO')
) AS v(name, reference)
WHERE LOWER(h.name) = LOWER(v.name) AND h.reference IS NULL;

UPDATE handle_models SET position = position + 100 WHERE reference IS NULL AND position < 100;

INSERT INTO handle_models (reference, name, shape, finish_hex, length_mm, center_distance_mm, width_mm, projection_mm,
    price_per_unit, image_url, supplier_url, position) VALUES
('POI9409106AL', 'Fil Ø10 aluminium anodisé, entraxe 96', 'bar', '#B8BBBE', 106, 96, 10, 30, 22, '/images/handles/lmc/fil-anodise.webp', 'https://www.lmcstore.com/fr/poignee-fil-10-mm.html', 1),
('POI9409138AL', 'Fil Ø10 aluminium anodisé, entraxe 128', 'bar', '#B8BBBE', 138, 128, 10, 30, 24, '/images/handles/lmc/fil-anodise.webp', 'https://www.lmcstore.com/fr/poignee-fil-10-mm.html', 2),
('POI9409170AL', 'Fil Ø10 aluminium anodisé, entraxe 160', 'bar', '#B8BBBE', 170, 160, 10, 30, 25, '/images/handles/lmc/fil-anodise.webp', 'https://www.lmcstore.com/fr/poignee-fil-10-mm.html', 3),
('POI9409202AL', 'Fil Ø10 aluminium anodisé, entraxe 192', 'bar', '#B8BBBE', 202, 192, 10, 30, 27, '/images/handles/lmc/fil-anodise.webp', 'https://www.lmcstore.com/fr/poignee-fil-10-mm.html', 4),
('POI9409266AL', 'Fil Ø10 aluminium anodisé, entraxe 256', 'bar', '#B8BBBE', 266, 256, 10, 30, 30, '/images/handles/lmc/fil-anodise.webp', 'https://www.lmcstore.com/fr/poignee-fil-10-mm.html', 5),
('POI9409362AL', 'Fil Ø10 aluminium anodisé, entraxe 352', 'bar', '#B8BBBE', 362, 352, 10, 30, 34, '/images/handles/lmc/fil-anodise.webp', 'https://www.lmcstore.com/fr/poignee-fil-10-mm.html', 6),
('POI518128CH', 'FLAT chromé, entraxe 128', 'flat_bar', '#D9DCDF', 143, 128, 8, 34, 26, '/images/handles/lmc/flat-chrome.webp', 'https://www.lmcstore.com/fr/poignee-flat.html', 7),
('POI518192CH', 'FLAT chromé, entraxe 192', 'flat_bar', '#D9DCDF', 207, 192, 8, 34, 29, '/images/handles/lmc/flat-chrome.webp', 'https://www.lmcstore.com/fr/poignee-flat.html', 8),
('POI518256CH', 'FLAT chromé, entraxe 256', 'flat_bar', '#D9DCDF', 271, 256, 8, 34, 32, '/images/handles/lmc/flat-chrome.webp', 'https://www.lmcstore.com/fr/poignee-flat.html', 9),
('POI518128NM', 'FLAT nickelé mat, entraxe 128', 'flat_bar', '#B5B7B9', 143, 128, 8, 34, 26, '/images/handles/lmc/flat-nickel-mat.webp', 'https://www.lmcstore.com/fr/poignee-flat.html', 10),
('POI518192NM', 'FLAT nickelé mat, entraxe 192', 'flat_bar', '#B5B7B9', 207, 192, 8, 34, 29, '/images/handles/lmc/flat-nickel-mat.webp', 'https://www.lmcstore.com/fr/poignee-flat.html', 11),
('POI518256NM', 'FLAT nickelé mat, entraxe 256', 'flat_bar', '#B5B7B9', 271, 256, 8, 34, 32, '/images/handles/lmc/flat-nickel-mat.webp', 'https://www.lmcstore.com/fr/poignee-flat.html', 12),
('POI518448NM', 'FLAT nickelé mat, entraxe 448', 'flat_bar', '#B5B7B9', 463, 448, 8, 34, 42, '/images/handles/lmc/flat-nickel-mat.webp', 'https://www.lmcstore.com/fr/poignee-flat.html', 13),
('POI286160NM', 'COMPACT nickelé mat, entraxe 160', 'flat_bar', '#B5B7B9', 172, 160, 8, 36, 27, '/images/handles/lmc/compact-nickel-mat-160.webp', 'https://www.lmcstore.com/fr/poignee-compact.html', 14),
('POI286320NM', 'COMPACT nickelé mat, entraxe 320', 'flat_bar', '#B5B7B9', 332, 320, 8, 36, 34, '/images/handles/lmc/compact-nickel-mat-320.webp', 'https://www.lmcstore.com/fr/poignee-compact.html', 15),
('POI286160NO', 'COMPACT noir, entraxe 160', 'flat_bar', '#1A1A1A', 172, 160, 8, 36, 27, '/images/handles/lmc/compact-noir-160.webp', 'https://www.lmcstore.com/fr/poignee-compact.html', 16),
('POI286320NO', 'COMPACT noir, entraxe 320', 'flat_bar', '#1A1A1A', 332, 320, 8, 36, 34, '/images/handles/lmc/compact-noir-320.webp', 'https://www.lmcstore.com/fr/poignee-compact.html', 17),
('POI117200NO', 'LOGGIC laiton noir, entraxe 200', 'flat_bar', '#1C1C1C', 223, 200, 20, 40, 38, '/images/handles/lmc/loggic-noir.webp', 'https://www.lmcstore.com/fr/poignee-de-tirage-loggic.html', 18),
('POI117200OR', 'LOGGIC laiton or satiné, entraxe 200', 'flat_bar', '#C9A66B', 223, 200, 20, 40, 38, '/images/handles/lmc/loggic-or-satine.webp', 'https://www.lmcstore.com/fr/poignee-de-tirage-loggic.html', 19),
('POI8160160MC', 'SAFARI cuir marron clair, entraxe 160', 'leather', '#A9703F', 224, 160, 24, 25, 34, '/images/handles/lmc/safari-marron-clair.webp', 'https://www.lmcstore.com/fr/poignee-cuir-safari.html', 20),
('POI8160160NO', 'SAFARI cuir noir, entraxe 160', 'leather', '#1F1F1F', 224, 160, 24, 25, 34, '/images/handles/lmc/safari-noir.webp', 'https://www.lmcstore.com/fr/poignee-cuir-safari.html', 21),
('POI449200BL', 'HEXXA blanc, longueur 200', 'profile', '#F2F2EF', 200, NULL, NULL, NULL, 32, '/images/handles/lmc/hexxa-blanc.webp', 'https://www.lmcstore.com/fr/poignee-a-encastrer-hexxa.html', 22),
('POI4491100BL', 'HEXXA blanc, longueur 1100', 'profile', '#F2F2EF', 1100, NULL, NULL, NULL, 75, '/images/handles/lmc/hexxa-blanc.webp', 'https://www.lmcstore.com/fr/poignee-a-encastrer-hexxa.html', 23),
('POI449200BR', 'HEXXA brun, longueur 200', 'profile', '#5A4636', 200, NULL, NULL, NULL, 32, '/images/handles/lmc/hexxa-brun.webp', 'https://www.lmcstore.com/fr/poignee-a-encastrer-hexxa.html', 24),
('POI4491100BR', 'HEXXA brun, longueur 1100', 'profile', '#5A4636', 1100, NULL, NULL, NULL, 75, '/images/handles/lmc/hexxa-brun.webp', 'https://www.lmcstore.com/fr/poignee-a-encastrer-hexxa.html', 25),
('POI449200NO', 'HEXXA noir mat, longueur 200', 'profile', '#222222', 200, NULL, NULL, NULL, 32, '/images/handles/lmc/hexxa-noir-mat.webp', 'https://www.lmcstore.com/fr/poignee-a-encastrer-hexxa.html', 26),
('POI4491100NO', 'HEXXA noir mat, longueur 1100', 'profile', '#222222', 1100, NULL, NULL, NULL, 75, '/images/handles/lmc/hexxa-noir-mat.webp', 'https://www.lmcstore.com/fr/poignee-a-encastrer-hexxa.html', 27),
('POI447IN', 'Cuvette LIVING inox brossé', 'cup', '#C9CCCF', 120, NULL, 40, 1, 28, '/images/handles/lmc/living-inox.webp', 'https://www.lmcstore.com/fr/poignee-cuvette-living.html', 28),
('POI447NO', 'Cuvette LIVING noire', 'cup', '#1C1C1C', 120, NULL, 40, 1, 28, '/images/handles/lmc/living-noir.webp', 'https://www.lmcstore.com/fr/poignee-cuvette-living.html', 29)
ON CONFLICT (reference) WHERE reference IS NOT NULL DO UPDATE SET
    length_mm = EXCLUDED.length_mm, center_distance_mm = EXCLUDED.center_distance_mm, width_mm = EXCLUDED.width_mm,
    projection_mm = EXCLUDED.projection_mm, image_url = EXCLUDED.image_url, supplier_url = EXCLUDED.supplier_url,
    position = EXCLUDED.position, updated_at = CURRENT_TIMESTAMP;
