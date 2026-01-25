-- Migration: Ajout du système de vérification email
-- Date: 2025-01-25

-- Ajouter la colonne email_verified à la table customers
ALTER TABLE customers ADD COLUMN email_verified INTEGER DEFAULT 0;

-- Créer la table pour stocker les codes de vérification
CREATE TABLE IF NOT EXISTS email_verifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    used INTEGER DEFAULT 0
);

-- Index pour recherche rapide par email
CREATE INDEX IF NOT EXISTS idx_email_verifications_email ON email_verifications(email);

-- Index pour nettoyage des codes expirés
CREATE INDEX IF NOT EXISTS idx_email_verifications_expires ON email_verifications(expires_at);
