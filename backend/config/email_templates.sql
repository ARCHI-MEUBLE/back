-- Table pour la configuration des templates d'emails
CREATE TABLE IF NOT EXISTS email_templates (
    id SERIAL PRIMARY KEY,
    template_name TEXT UNIQUE NOT NULL,
    subject TEXT NOT NULL,
    header_text TEXT,
    footer_text TEXT,
    show_logo BOOLEAN DEFAULT TRUE,
    show_gallery BOOLEAN DEFAULT TRUE,
    gallery_images TEXT, -- JSON array des noms d'images
    custom_css TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Templates par défaut
INSERT INTO email_templates (template_name, subject, header_text, footer_text, gallery_images) VALUES
('confirmation', 'Confirmation de votre rendez-vous ArchiMeuble', '✓ Rendez-vous confirmé', 'ArchiMeuble - Meubles sur mesure', '["biblio.jpg", "buffet.jpg", "dressing.jpg"]'),
('reminder_24h', 'Rappel : Votre rendez-vous ArchiMeuble demain', '⏰ Rendez-vous demain !', 'ArchiMeuble - Meubles sur mesure', '["biblio.jpg", "buffet.jpg", "dressing.jpg"]'),
('reminder_1h', 'Votre rendez-vous ArchiMeuble dans 1h', '⏰ Rendez-vous dans 1h !', 'ArchiMeuble - Meubles sur mesure', '[]'),
('admin_notification', 'Nouveau RDV Calendly - ArchiMeuble', 'Nouveau Rendez-vous ArchiMeuble', '', '[]')
ON CONFLICT DO NOTHING;
