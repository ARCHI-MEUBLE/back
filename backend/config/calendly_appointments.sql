-- Table des rendez-vous Calendly
-- Stocke les informations des rendez-vous pour permettre l'envoi de rappels

CREATE TABLE IF NOT EXISTS calendly_appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendly_event_id TEXT UNIQUE NOT NULL,
    client_name TEXT NOT NULL,
    client_email TEXT NOT NULL,
    event_type TEXT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    timezone TEXT DEFAULT 'Europe/Paris',
    config_url TEXT,
    additional_notes TEXT,
    meeting_url TEXT,
    phone_number TEXT,
    status TEXT DEFAULT 'scheduled',
    confirmation_sent BOOLEAN DEFAULT 0,
    reminder_24h_sent BOOLEAN DEFAULT 0,
    reminder_1h_sent BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Index pour optimiser les requêtes
CREATE INDEX IF NOT EXISTS idx_calendly_start_time ON calendly_appointments(start_time);
CREATE INDEX IF NOT EXISTS idx_calendly_status ON calendly_appointments(status);
CREATE INDEX IF NOT EXISTS idx_calendly_reminder_24h_sent ON calendly_appointments(reminder_24h_sent);
CREATE INDEX IF NOT EXISTS idx_calendly_reminder_1h_sent ON calendly_appointments(reminder_1h_sent);
CREATE INDEX IF NOT EXISTS idx_calendly_email ON calendly_appointments(client_email);
