-- ArchiMeuble - Table pour les liens de paiement sécurisés
-- Date : 2025-12-27
-- Description : Permet aux admins de générer des liens de paiement sécurisés pour les clients

-- Table des liens de paiement
CREATE TABLE IF NOT EXISTS payment_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    token TEXT NOT NULL UNIQUE, -- Token sécurisé pour l'URL (ex: uuid v4)
    status TEXT DEFAULT 'active', -- active, used, expired, revoked
    expires_at DATETIME NOT NULL, -- Date d'expiration (ex: 30 jours)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    accessed_at DATETIME, -- Première fois que le lien a été consulté
    paid_at DATETIME, -- Quand le paiement a été effectué via ce lien
    created_by_admin TEXT, -- Email de l'admin qui a créé le lien
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Index pour performances
CREATE INDEX IF NOT EXISTS idx_payment_links_token ON payment_links(token);
CREATE INDEX IF NOT EXISTS idx_payment_links_order ON payment_links(order_id);
CREATE INDEX IF NOT EXISTS idx_payment_links_status ON payment_links(status);
CREATE INDEX IF NOT EXISTS idx_payment_links_expires ON payment_links(expires_at);
