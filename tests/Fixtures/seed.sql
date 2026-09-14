INSERT INTO categories (name, slug, description, image_url, display_order, is_active) VALUES
('Meubles TV', 'meubles-tv', 'Meubles pour le salon', '/uploads/categories/tv.png', 1, TRUE),
('Bibliothèques', 'bibliotheques', 'Rangements muraux', NULL, 2, FALSE);

INSERT INTO catalogue_items (name, category, description, material, dimensions, unit_price, unit, stock_quantity, min_order_quantity, is_available, image_url, tags) VALUES
('Poignée laiton', 'Quincaillerie', 'Poignée en laiton brossé', 'Laiton', '120x20', 12.50, 'piece', 100, 1, TRUE, '/uploads/catalogue/poignee.png', 'poignee,laiton'),
('Charnière invisible', 'Quincaillerie', NULL, 'Acier', NULL, 4.90, 'piece', 500, 2, TRUE, NULL, NULL);

INSERT INTO catalogue_item_variations (catalogue_item_id, color_name, image_url, is_default) VALUES
(1, 'Laiton brossé', '/uploads/catalogue/poignee-laiton.png', TRUE),
(1, 'Noir mat', '/uploads/catalogue/poignee-noir.png', FALSE);

INSERT INTO facade_materials (name, color_hex, texture_url, price_modifier, price_per_m2, is_active) VALUES
('Chêne brun', '#8B5A2B', '/textures/chene_brun.png', 0, 150.00, TRUE),
('Blanc premium', '#F5F5F5', '/textures/blanc_premium.png', 10, 120.00, FALSE);

INSERT INTO facade_drilling_types (name, description, icon_svg, price, is_active) VALUES
('Charnière standard', 'Perçage 35 mm', '<svg></svg>', 2.50, TRUE),
('Poignée', 'Deux trous', NULL, 1.00, TRUE);

INSERT INTO facade_settings (setting_key, setting_value, description) VALUES
('material_price_per_m2', '150', 'Prix matière'),
('hinge_base_price', '5', 'Prix de base charnière'),
('hinge_coefficient', '0.01', 'Coefficient charnière'),
('hinge_edge_margin', '100', 'Marge bord'),
('hinge_hole_diameter', '35', 'Diamètre perçage'),
('max_width', '1200', 'Largeur max'),
('max_height', '2400', 'Hauteur max'),
('fixed_depth', '19', 'Épaisseur');

INSERT INTO realisations (titre, description, image_url, date_projet, categorie, lieu, dimensions) VALUES
('Bibliothèque sur mesure', 'Chêne massif', '/uploads/realisations/biblio.jpg', '2026-03', 'Bibliothèque', 'Lille', '2400x300x2200');

INSERT INTO realisation_images (realisation_id, image_url, ordre) VALUES
(1, '/uploads/realisations/biblio-1.jpg', 1),
(1, '/uploads/realisations/biblio-2.jpg', 2);

INSERT INTO avis (user_id, author_name, rating, text, date) VALUES
('u-1', 'Marie', 5, 'Superbe meuble', '2026-01-10'),
(NULL, 'Paul', 4, 'Très bon travail', '2026-02-02');

INSERT INTO calendly_appointments (calendly_event_id, client_name, client_email, event_type, start_time, end_time, timezone, status) VALUES
('evt-1', 'Alice Martin', 'alice@example.test', 'Consultation téléphonique', '2027-01-15 10:00:00', '2027-01-15 10:30:00', 'Europe/Paris', 'scheduled'),
('evt-2', 'Bob Durand', 'bob@example.test', 'Visio', '2027-01-16 14:00:00', '2027-01-16 14:45:00', 'Europe/Paris', 'scheduled');

INSERT INTO quote_requests (first_name, last_name, email, phone, description, status) VALUES
('Jean', 'Dupont', 'jean@example.test', '0600000000', 'Un dressing', 'pending');

INSERT INTO templates (name, description, prompt, config_data, image_url, category, price, is_active) VALUES
('Template TV', 'Base', 'M1(1700,500,730)EFbV3(,T,)', '{}', NULL, 'Meubles TV', 899.00, TRUE);

INSERT INTO configurations (id, user_id, user_session, template_id, config_string, prompt, price, glb_url, dxf_url, status) VALUES
(1, '1', NULL, 1, '{"name":"Ma config","dimensions":{"width":1700}}', 'M1(1700,500,730)EFbV3(,T,)', 899.50, '/models/seed.glb', '/models/seed.dxf', 'validee'),
(2, '1', NULL, 1, '{"name":"Brouillon"}', 'M1(1200,350,650)EFbV4(,,T,)', 699.00, '/models/seed2.glb', NULL, 'en_attente_validation'),
(3, '1', NULL, 2, '{"name":"Pour commande admin"}', 'M1(2000,400,600)EFbV2(T,T)', 1099.00, '/models/seed3.glb', NULL, 'validee');
SELECT setval('configurations_id_seq', (SELECT MAX(id) FROM configurations));

