-- 0017 sliding doors (zone content 'sliding_door', prompt code Pc): 2 panels up to 1600 mm of opening, 3 above, on a
-- black two-track plastic rail set in a groove of the top and bottom of the opening. Each panel is priced like a door
-- leaf (fronts.door.fixed_price + facade); the rail and the groove are priced per metre, top and bottom.
INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description) VALUES
('fronts', 'sliding', 'rail_price_per_m', 15, 'eur_linear_m', 'Rail plastique noir à deux voies d''une porte coulissante, par mètre (posé en haut et en bas de l''ouverture)'),
('fronts', 'sliding', 'groove_price_per_m', 6, 'eur_linear_m', 'Défonce du rail dans le dessus et le dessous d''une porte coulissante, par mètre')
ON CONFLICT (category, item_type, param_name) DO NOTHING;
