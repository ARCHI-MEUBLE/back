-- Migration pour ajouter les paramètres de tarification des façades
-- Créé le 2026-01-12

-- Ajouter les paramètres de prix dans facade_settings
INSERT INTO facade_settings (setting_key, setting_value, description) VALUES
    ('hinge_base_price', '34.20', 'Prix de base d''une charnière (€)'),
    ('hinge_coefficient', '0.05', 'Coefficient multiplicateur par nombre de charnières (appliqué au prix total)'),
    ('material_price_per_m2', '150.00', 'Prix de base du matériau au m² (€)')
ON CONFLICT DO NOTHING;

-- Note: Le calcul final sera :
-- Prix total = (Surface_m² × Prix_matériau_m²) + (Prix_base_charnières × Nb_charnières) + (Prix_total × Coef_charnières × Nb_charnières)
-- ou simplifié:
-- Prix_surface = Surface_m² × Prix_matériau_m²
-- Prix_charnières = Prix_base × Nb_charnières
-- Supplément = (Prix_surface + Prix_charnières) × (Coef × Nb_charnières)
-- Total = Prix_surface + Prix_charnières + Supplément
